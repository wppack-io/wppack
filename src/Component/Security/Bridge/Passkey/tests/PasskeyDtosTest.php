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

namespace WPPack\Component\Security\Bridge\Passkey\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\Passkey\Badge\PasskeyCredentialBadge;
use WPPack\Component\Security\Bridge\Passkey\Configuration\PasskeyConfiguration;
use WPPack\Component\Security\Bridge\Passkey\Storage\AaguidResolver;
use WPPack\Component\Security\Bridge\Passkey\Storage\PasskeyCredential;

#[CoversClass(PasskeyConfiguration::class)]
#[CoversClass(PasskeyCredentialBadge::class)]
#[CoversClass(AaguidResolver::class)]
#[CoversClass(PasskeyCredential::class)]
final class PasskeyDtosTest extends TestCase
{
    // ── PasskeyConfiguration ────────────────────────────────────────

    #[Test]
    public function passkeyConfigurationDefaultsMatchWebAuthnRecommendations(): void
    {
        $config = new PasskeyConfiguration();

        self::assertSame('', $config->rpName);
        self::assertSame('', $config->rpId);
        self::assertSame('', $config->origin);
        self::assertSame(60_000, $config->timeout);
        self::assertSame('none', $config->attestation);
        self::assertSame('preferred', $config->userVerification);
        self::assertSame('required', $config->residentKey);
        self::assertSame([-7, -257], $config->algorithms, 'default COSE algorithms are ES256 (-7) and RS256 (-257)');
        self::assertSame('', $config->authenticatorAttachment);
        self::assertSame(3, $config->maxCredentialsPerUser);
    }

    #[Test]
    public function passkeyConfigurationAllowsAllFieldOverrides(): void
    {
        $config = new PasskeyConfiguration(
            rpName: 'My Site',
            rpId: 'example.test',
            origin: 'https://example.test',
            timeout: 30_000,
            attestation: 'direct',
            userVerification: 'required',
            residentKey: 'preferred',
            algorithms: [-8, -35],
            authenticatorAttachment: 'platform',
            maxCredentialsPerUser: 10,
        );

        self::assertSame('My Site', $config->rpName);
        self::assertSame('example.test', $config->rpId);
        self::assertSame('https://example.test', $config->origin);
        self::assertSame(30_000, $config->timeout);
        self::assertSame('direct', $config->attestation);
        self::assertSame('required', $config->userVerification);
        self::assertSame('preferred', $config->residentKey);
        self::assertSame([-8, -35], $config->algorithms);
        self::assertSame('platform', $config->authenticatorAttachment);
        self::assertSame(10, $config->maxCredentialsPerUser);
    }

    // ── PasskeyCredentialBadge ─────────────────────────────────────

    #[Test]
    public function passkeyCredentialBadgeCarriesReplayCounterAndBackupFlag(): void
    {
        $badge = new PasskeyCredentialBadge('cred-1', newCounter: 42, backupEligible: true);

        self::assertSame('cred-1', $badge->credentialId);
        self::assertSame(42, $badge->newCounter);
        self::assertTrue($badge->backupEligible);
        self::assertTrue($badge->isResolved());
    }

    // ── AaguidResolver ─────────────────────────────────────────────

    #[Test]
    public function aaguidResolverMatchesKnownDevicesCaseInsensitively(): void
    {
        self::assertSame('iCloud Passkey', AaguidResolver::resolve('fbfc3007-154e-4ecc-8c0b-6e020557d7bd'));
        self::assertSame('iCloud Passkey', AaguidResolver::resolve('FBFC3007-154E-4ECC-8C0B-6E020557D7BD'));
        self::assertSame('Windows Hello', AaguidResolver::resolve('0ea242b4-43c4-4a1b-8b17-dd6d0b6baec6'));
        self::assertSame('1Password', AaguidResolver::resolve('bada5566-a7aa-401f-bd96-45619a55120d'));
    }

    #[Test]
    public function aaguidResolverFallsBackToGenericPasskeyLabel(): void
    {
        self::assertSame('Passkey', AaguidResolver::resolve('not-a-known-aaguid'));
        self::assertSame('Passkey', AaguidResolver::resolve(''));
    }

    // ── PasskeyCredential ─────────────────────────────────────────

    #[Test]
    public function passkeyCredentialDtoCarriesEveryField(): void
    {
        $created = new \DateTimeImmutable('2024-01-01T00:00:00+00:00');
        $lastUsed = new \DateTimeImmutable('2024-02-01T00:00:00+00:00');

        $credential = new PasskeyCredential(
            id: 7,
            userId: 42,
            credentialId: 'cred-abc',
            publicKey: 'PK',
            counter: 5,
            transports: ['internal', 'hybrid'],
            deviceName: 'YubiKey 5 NFC',
            aaguid: 'cb69481e-8ff7-4039-93ec-0a2729a154a8',
            backupEligible: false,
            createdAt: $created,
            lastUsedAt: $lastUsed,
        );

        self::assertSame(7, $credential->id);
        self::assertSame(42, $credential->userId);
        self::assertSame('cred-abc', $credential->credentialId);
        self::assertSame('PK', $credential->publicKey);
        self::assertSame(5, $credential->counter);
        self::assertSame(['internal', 'hybrid'], $credential->transports);
        self::assertSame('YubiKey 5 NFC', $credential->deviceName);
        self::assertSame('cb69481e-8ff7-4039-93ec-0a2729a154a8', $credential->aaguid);
        self::assertFalse($credential->backupEligible);
        self::assertSame($created, $credential->createdAt);
        self::assertSame($lastUsed, $credential->lastUsedAt);
    }

    #[Test]
    public function passkeyCredentialLastUsedIsNullable(): void
    {
        $credential = new PasskeyCredential(
            id: 1,
            userId: 1,
            credentialId: 'x',
            publicKey: 'x',
            counter: 0,
            transports: [],
            deviceName: 'Passkey',
            aaguid: '00000000-0000-0000-0000-000000000000',
            backupEligible: true,
            createdAt: new \DateTimeImmutable(),
            lastUsedAt: null,
        );

        self::assertNull($credential->lastUsedAt);
    }
}
