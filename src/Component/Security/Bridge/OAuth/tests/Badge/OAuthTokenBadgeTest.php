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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Badge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Badge\OAuthTokenBadge;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;

#[CoversClass(OAuthTokenBadge::class)]
final class OAuthTokenBadgeTest extends TestCase
{
    private OAuthTokenSet $tokenSet;

    protected function setUp(): void
    {
        $this->tokenSet = new OAuthTokenSet(
            accessToken: 'access-token-value',
            tokenType: 'Bearer',
            idToken: 'id-token-jwt',
        );
    }

    #[Test]
    public function getSubject(): void
    {
        $badge = new OAuthTokenBadge(
            'user@example.com',
            ['email' => 'user@example.com'],
            $this->tokenSet,
        );

        self::assertSame('user@example.com', $badge->getSubject());
    }

    #[Test]
    public function getClaims(): void
    {
        $claims = [
            'sub' => '12345',
            'email' => 'user@example.com',
            'name' => 'Test User',
        ];

        $badge = new OAuthTokenBadge('12345', $claims, $this->tokenSet);

        self::assertSame($claims, $badge->getClaims());
    }

    #[Test]
    public function getClaimReturnsValue(): void
    {
        $badge = new OAuthTokenBadge(
            '12345',
            ['email' => 'user@example.com', 'name' => 'Test User'],
            $this->tokenSet,
        );

        self::assertSame('user@example.com', $badge->getClaim('email'));
    }

    #[Test]
    public function getClaimReturnsNullForMissingKey(): void
    {
        $badge = new OAuthTokenBadge('12345', [], $this->tokenSet);

        self::assertNull($badge->getClaim('missing'));
    }

    #[Test]
    public function getTokenSet(): void
    {
        $badge = new OAuthTokenBadge('12345', [], $this->tokenSet);

        self::assertSame($this->tokenSet, $badge->getTokenSet());
    }

    #[Test]
    public function isResolvedAlwaysReturnsTrue(): void
    {
        $badge = new OAuthTokenBadge('12345', [], $this->tokenSet);

        self::assertTrue($badge->isResolved());
    }
}
