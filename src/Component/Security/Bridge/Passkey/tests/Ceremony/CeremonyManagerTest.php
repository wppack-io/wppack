<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Bridge\Passkey\Tests\Ceremony;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Storage\CredentialRepositoryInterface;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(CeremonyManager::class)]
final class CeremonyManagerTest extends TestCase
{
    private function ceremony(
        PasskeyConfiguration $config = new PasskeyConfiguration(),
        ?CredentialRepositoryInterface $repository = null,
        ?BlogContextInterface $blogContext = null,
    ): CeremonyManager {
        return new CeremonyManager(
            config: $config,
            repository: $repository ?? new InMemoryCredentialRepository(),
            transients: new TransientManager(),
            blogContext: $blogContext,
        );
    }

    private function user(): \WP_User
    {
        $id = (int) wp_insert_user([
            'user_login' => 'passkey_' . uniqid(),
            'user_email' => 'passkey_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'display_name' => 'Passkey User',
        ]);

        return new \WP_User($id);
    }

    #[Test]
    public function createRegistrationOptionsProducesChallengeAndStoresTransient(): void
    {
        $user = $this->user();
        $result = $this->ceremony()->createRegistrationOptions($user);

        self::assertInstanceOf(PublicKeyCredentialCreationOptions::class, $result['options']);
        self::assertStringStartsWith('wppack_passkey_challenge_', $result['challengeKey']);
        self::assertSame(32, \strlen($result['options']->challenge));

        $stored = get_transient($result['challengeKey']);
        self::assertIsArray($stored);
        self::assertSame('registration', $stored['type']);
        self::assertSame($user->ID, $stored['userId']);
        self::assertSame(PublicKeyCredentialCreationOptions::class, $stored['optionsClass']);
        self::assertIsString($stored['options']);
    }

    #[Test]
    public function createRegistrationOptionsExcludesExistingCredentials(): void
    {
        $user = $this->user();
        $repo = new InMemoryCredentialRepository();

        $existing = new PasskeyCredential(
            id: 1,
            userId: $user->ID,
            credentialId: 'AAAA',
            publicKey: 'key',
            counter: 0,
            transports: ['internal'],
            deviceName: 'Test',
            aaguid: '00000000-0000-0000-0000-000000000000',
            backupEligible: false,
            createdAt: new \DateTimeImmutable(),
            lastUsedAt: null,
        );
        $repo->save($existing);

        $result = $this->ceremony(repository: $repo)->createRegistrationOptions($user);

        self::assertCount(1, $result['options']->excludeCredentials);
    }

    #[Test]
    public function createRegistrationOptionsUsesConfiguredRpAndAlgorithms(): void
    {
        $user = $this->user();

        $result = $this->ceremony(new PasskeyConfiguration(
            rpName: 'WPPack Test',
            rpId: 'example.test',
            algorithms: [-7, -257, -8],
            authenticatorAttachment: 'platform',
            timeout: 42_000,
        ))->createRegistrationOptions($user);

        self::assertSame('WPPack Test', $result['options']->rp->name);
        self::assertSame('example.test', $result['options']->rp->id);
        self::assertCount(3, $result['options']->pubKeyCredParams);
        self::assertSame(42_000, $result['options']->timeout);
    }

    #[Test]
    public function createRegistrationOptionsFallsBackToEmptyAlgorithmsWithDefaults(): void
    {
        $user = $this->user();

        $result = $this->ceremony(new PasskeyConfiguration(algorithms: []))
            ->createRegistrationOptions($user);

        self::assertCount(2, $result['options']->pubKeyCredParams, 'empty [] falls back to [ES256, RS256]');
    }

    #[Test]
    public function createAuthenticationOptionsProducesChallengeAndTransient(): void
    {
        $result = $this->ceremony()->createAuthenticationOptions();

        self::assertInstanceOf(PublicKeyCredentialRequestOptions::class, $result['options']);
        self::assertStringStartsWith('wppack_passkey_challenge_', $result['challengeKey']);
        self::assertSame(32, \strlen($result['options']->challenge));

        $stored = get_transient($result['challengeKey']);
        self::assertIsArray($stored);
        self::assertSame('authentication', $stored['type']);
        self::assertArrayNotHasKey('userId', $stored);
        self::assertSame(PublicKeyCredentialRequestOptions::class, $stored['optionsClass']);
    }

    #[Test]
    public function consumeChallengeReturnsStoredDataAndDeletesIt(): void
    {
        $user = $this->user();
        $ceremony = $this->ceremony();
        $result = $ceremony->createRegistrationOptions($user);

        $consumed = $ceremony->consumeChallenge($result['challengeKey']);

        self::assertIsArray($consumed);
        self::assertSame('registration', $consumed['type']);
        self::assertFalse(get_transient($result['challengeKey']), 'challenge is single-use');
    }

    #[Test]
    public function consumeChallengeReturnsNullForMissingKey(): void
    {
        self::assertNull($this->ceremony()->consumeChallenge('nonexistent-key'));
    }

    #[Test]
    public function consumeChallengeReturnsNullForNonArrayTransient(): void
    {
        set_transient('wppack_passkey_challenge_notarray', 'string-value', 300);

        self::assertNull($this->ceremony()->consumeChallenge('wppack_passkey_challenge_notarray'));

        delete_transient('wppack_passkey_challenge_notarray');
    }

    #[Test]
    public function deserializeOptionsRoundTripsCreationOptions(): void
    {
        $user = $this->user();
        $ceremony = $this->ceremony();
        $result = $ceremony->createRegistrationOptions($user);
        $stored = $ceremony->consumeChallenge($result['challengeKey']);

        self::assertIsArray($stored);
        $deserialized = $ceremony->deserializeOptions($stored['options'], $stored['optionsClass']);

        self::assertInstanceOf(PublicKeyCredentialCreationOptions::class, $deserialized);
    }

    #[Test]
    public function rpNameDefaultsToBlogname(): void
    {
        $user = $this->user();

        $result = $this->ceremony(new PasskeyConfiguration())->createRegistrationOptions($user);

        self::assertSame(get_bloginfo('name'), $result['options']->rp->name);
    }
}
