<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\State;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\State\StoredState;

#[CoversClass(StoredState::class)]
final class StoredStateTest extends TestCase
{
    #[Test]
    public function constructorGetters(): void
    {
        $state = new StoredState(
            nonce: 'nonce-value',
            codeVerifier: 'verifier-value',
            returnTo: '/dashboard',
            createdAt: 1700000000,
        );

        self::assertSame('nonce-value', $state->getNonce());
        self::assertSame('verifier-value', $state->getCodeVerifier());
        self::assertSame('/dashboard', $state->getReturnTo());
        self::assertSame(1700000000, $state->getCreatedAt());
    }

    #[Test]
    public function optionalFieldsDefaultToNull(): void
    {
        $state = new StoredState(nonce: 'nonce-value');

        self::assertNull($state->getCodeVerifier());
        self::assertNull($state->getReturnTo());
        self::assertSame(0, $state->getCreatedAt());
    }

    #[Test]
    public function createFactorySetsCreatedAtToCurrentTime(): void
    {
        $before = time();
        $state = StoredState::create('nonce-value');
        $after = time();

        self::assertSame('nonce-value', $state->getNonce());
        self::assertGreaterThanOrEqual($before, $state->getCreatedAt());
        self::assertLessThanOrEqual($after, $state->getCreatedAt());
    }

    #[Test]
    public function createFactoryWithAllArguments(): void
    {
        $state = StoredState::create(
            nonce: 'nonce-value',
            codeVerifier: 'verifier-value',
            returnTo: '/admin',
        );

        self::assertSame('nonce-value', $state->getNonce());
        self::assertSame('verifier-value', $state->getCodeVerifier());
        self::assertSame('/admin', $state->getReturnTo());
    }

    #[Test]
    public function isExpiredReturnsFalseForRecentState(): void
    {
        $state = StoredState::create('nonce-value');

        self::assertFalse($state->isExpired());
    }

    #[Test]
    public function isExpiredReturnsTrueForOldState(): void
    {
        $state = new StoredState(
            nonce: 'nonce-value',
            createdAt: time() - 700,
        );

        self::assertTrue($state->isExpired());
    }

    #[Test]
    public function isExpiredWithCustomTtl(): void
    {
        $state = new StoredState(
            nonce: 'nonce-value',
            createdAt: time() - 120,
        );

        self::assertFalse($state->isExpired(300));
        self::assertTrue($state->isExpired(60));
    }

    #[Test]
    public function isExpiredReturnsTrueAtExactExpiry(): void
    {
        $state = new StoredState(
            nonce: 'nonce-value',
            createdAt: time() - 600,
        );

        self::assertTrue($state->isExpired(600));
    }
}
