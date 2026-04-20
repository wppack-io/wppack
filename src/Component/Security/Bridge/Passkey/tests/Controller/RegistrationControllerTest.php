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

namespace WPPack\Component\Security\Bridge\Passkey\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Bridge\Passkey\Ceremony\CeremonyManager;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Controller\RegistrationController;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;
use WPPack\Component\Security\Bridge\Passkey\Tests\Ceremony\InMemoryCredentialRepository;
use WPPack\Component\Transient\TransientManager;

#[CoversClass(RegistrationController::class)]
final class RegistrationControllerTest extends TestCase
{
    private int $userId = 0;

    protected function setUp(): void
    {
        $this->userId = (int) wp_insert_user([
            'user_login' => 'pk_reg_' . uniqid(),
            'user_email' => 'pk_reg_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);
        wp_set_current_user($this->userId);
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        wp_delete_user($this->userId);
    }

    private function controller(
        PasskeyConfiguration $config = new PasskeyConfiguration(rpId: 'example.test'),
        ?InMemoryCredentialRepository $repo = null,
    ): RegistrationController {
        $repo = $repo ?? new InMemoryCredentialRepository();

        return new RegistrationController(
            new CeremonyManager($config, $repo, new TransientManager()),
            $repo,
            $config,
            new AuthenticationSession(),
            new NullLogger(),
        );
    }

    private function request(array $body = []): \WP_REST_Request
    {
        $req = new \WP_REST_Request();
        $req->set_header('content-type', 'application/json');
        $req->set_body(json_encode($body, \JSON_THROW_ON_ERROR));

        return $req;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\WPPack\Component\HttpFoundation\JsonResponse $response): array
    {
        /** @var array<string, mixed> */
        return json_decode($response->content, true, flags: \JSON_THROW_ON_ERROR);
    }

    private function credential(int $id, int $userId): PasskeyCredential
    {
        return new PasskeyCredential(
            id: $id,
            userId: $userId,
            credentialId: 'cred-' . $id,
            publicKey: 'pk',
            counter: 0,
            transports: [],
            deviceName: 'x',
            aaguid: '00000000-0000-0000-0000-000000000000',
            backupEligible: false,
            createdAt: new \DateTimeImmutable(),
            lastUsedAt: null,
        );
    }

    #[Test]
    public function optionsReturnsChallengeEnvelope(): void
    {
        $response = $this->controller()->options($this->request());

        self::assertSame(200, $response->statusCode);
        $body = $this->decode($response);
        self::assertArrayHasKey('challengeKey', $body);
        self::assertArrayHasKey('challenge', $body);
        self::assertArrayHasKey('rp', $body);
        self::assertArrayHasKey('user', $body);
        self::assertStringStartsWith('wppack_passkey_challenge_', $body['challengeKey']);
    }

    #[Test]
    public function optionsRejectsWhenMaxCredentialsReached(): void
    {
        $repo = new InMemoryCredentialRepository();
        $config = new PasskeyConfiguration(rpId: 'example.test', maxCredentialsPerUser: 2);

        $repo->save($this->credential(1, $this->userId));
        $repo->save($this->credential(2, $this->userId));

        $controller = $this->controller($config, $repo);
        $response = $controller->options($this->request());

        self::assertSame(400, $response->statusCode);
        self::assertSame('Maximum number of passkeys reached.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyRejectsMissingChallengeKey(): void
    {
        $response = $this->controller()->verify($this->request([]));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Invalid or expired challenge.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyRejectsWhenChallengeTypeMismatches(): void
    {
        // Stash an "authentication" challenge under some key and try to verify registration
        set_transient('wppack_passkey_challenge_bad', [
            'type' => 'authentication',
            'options' => '{}',
            'optionsClass' => \Webauthn\PublicKeyCredentialRequestOptions::class,
        ], 300);

        $response = $this->controller()->verify($this->request([
            'challengeKey' => 'wppack_passkey_challenge_bad',
        ]));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Invalid or expired challenge.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyFailsGracefullyOnMalformedCredential(): void
    {
        // Prime a valid registration challenge
        $optionsRes = $this->controller()->options($this->request());
        $body = $this->decode($optionsRes);

        // Malformed credential JSON — serializer throws, controller maps to 400
        $response = $this->controller()->verify($this->request([
            'challengeKey' => $body['challengeKey'],
            'id' => 'bogus',
            'type' => 'public-key',
            'rawId' => 'not-base64url',
            'response' => [],
        ]));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Passkey registration failed.', $this->decode($response)['error']);
    }

    #[Test]
    public function verifyConsumesChallengeExactlyOnce(): void
    {
        $optionsRes = $this->controller()->options($this->request());
        $challengeKey = $this->decode($optionsRes)['challengeKey'];

        $malformed = [
            'challengeKey' => $challengeKey,
            'id' => 'bogus',
            'type' => 'public-key',
            'rawId' => 'not-base64url',
            'response' => [],
        ];

        // First call fails during validation but consumes the challenge
        $this->controller()->verify($this->request($malformed));

        // Second call must see "expired" error, proving single-use semantics
        $second = $this->controller()->verify($this->request(['challengeKey' => $challengeKey]));
        self::assertSame(400, $second->statusCode);
        self::assertSame('Invalid or expired challenge.', $this->decode($second)['error']);
    }

    #[Test]
    public function isLoggedInReflectsCurrentUser(): void
    {
        self::assertTrue($this->controller()->isLoggedIn($this->request()));
        wp_set_current_user(0);
        self::assertFalse($this->controller()->isLoggedIn($this->request()));
    }
}
