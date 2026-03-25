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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;

#[CoversClass(OAuthTokenSet::class)]
final class OAuthTokenSetTest extends TestCase
{
    #[Test]
    public function requiredGetters(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token-value',
            tokenType: 'Bearer',
        );

        self::assertSame('access-token-value', $tokenSet->getAccessToken());
        self::assertSame('Bearer', $tokenSet->getTokenType());
    }

    #[Test]
    public function optionalFieldsDefaultToNull(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
        );

        self::assertNull($tokenSet->getIdToken());
        self::assertNull($tokenSet->getRefreshToken());
        self::assertNull($tokenSet->getExpiresIn());
        self::assertNull($tokenSet->getScope());
        self::assertNull($tokenSet->getIssuedAt());
    }

    #[Test]
    public function allGetters(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
            idToken: 'id-token-jwt',
            refreshToken: 'refresh-token-value',
            expiresIn: 3600,
            scope: 'openid email profile',
            issuedAt: 1700000000,
        );

        self::assertSame('id-token-jwt', $tokenSet->getIdToken());
        self::assertSame('refresh-token-value', $tokenSet->getRefreshToken());
        self::assertSame(3600, $tokenSet->getExpiresIn());
        self::assertSame('openid email profile', $tokenSet->getScope());
        self::assertSame(1700000000, $tokenSet->getIssuedAt());
    }

    #[Test]
    public function isExpiredReturnsFalseWhenExpiresInIsNull(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
        );

        self::assertFalse($tokenSet->isExpired());
    }

    #[Test]
    public function isExpiredReturnsFalseWhenIssuedAtIsNull(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
            expiresIn: 3600,
        );

        self::assertFalse($tokenSet->isExpired());
    }

    #[Test]
    public function isExpiredReturnsTrueWhenTokenExpired(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            issuedAt: time() - 7200,
        );

        self::assertTrue($tokenSet->isExpired());
    }

    #[Test]
    public function isExpiredReturnsFalseWhenTokenNotExpired(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
            expiresIn: 3600,
            issuedAt: time(),
        );

        self::assertFalse($tokenSet->isExpired());
    }

    #[Test]
    public function isExpiredReturnsTrueAtExactExpiry(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
            expiresIn: 0,
            issuedAt: time(),
        );

        self::assertTrue($tokenSet->isExpired());
    }

    #[Test]
    public function hasRefreshTokenReturnsTrueWhenPresent(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
            refreshToken: 'refresh-token',
        );

        self::assertTrue($tokenSet->hasRefreshToken());
    }

    #[Test]
    public function hasRefreshTokenReturnsFalseWhenNull(): void
    {
        $tokenSet = new OAuthTokenSet(
            accessToken: 'access-token',
            tokenType: 'Bearer',
        );

        self::assertFalse($tokenSet->hasRefreshToken());
    }

    #[Test]
    public function fromArrayWithFullResponse(): void
    {
        $data = [
            'access_token' => 'access-token-123',
            'token_type' => 'Bearer',
            'id_token' => 'eyJhbGciOiJSUzI1NiJ9.test',
            'refresh_token' => 'refresh-token-456',
            'expires_in' => 3600,
            'scope' => 'openid email',
        ];

        $tokenSet = OAuthTokenSet::fromArray($data);

        self::assertSame('access-token-123', $tokenSet->getAccessToken());
        self::assertSame('Bearer', $tokenSet->getTokenType());
        self::assertSame('eyJhbGciOiJSUzI1NiJ9.test', $tokenSet->getIdToken());
        self::assertSame('refresh-token-456', $tokenSet->getRefreshToken());
        self::assertSame(3600, $tokenSet->getExpiresIn());
        self::assertSame('openid email', $tokenSet->getScope());
        self::assertNotNull($tokenSet->getIssuedAt());
        self::assertEqualsWithDelta(time(), $tokenSet->getIssuedAt(), 2);
    }

    #[Test]
    public function fromArrayWithMinimalResponse(): void
    {
        $data = [
            'access_token' => 'access-token-minimal',
        ];

        $tokenSet = OAuthTokenSet::fromArray($data);

        self::assertSame('access-token-minimal', $tokenSet->getAccessToken());
        self::assertSame('Bearer', $tokenSet->getTokenType());
        self::assertNull($tokenSet->getIdToken());
        self::assertNull($tokenSet->getRefreshToken());
        self::assertNull($tokenSet->getExpiresIn());
        self::assertNull($tokenSet->getScope());
    }

    #[Test]
    public function fromArraySetsIssuedAtToCurrentTime(): void
    {
        $before = time();
        $tokenSet = OAuthTokenSet::fromArray(['access_token' => 'test']);
        $after = time();

        self::assertGreaterThanOrEqual($before, $tokenSet->getIssuedAt());
        self::assertLessThanOrEqual($after, $tokenSet->getIssuedAt());
    }
}
