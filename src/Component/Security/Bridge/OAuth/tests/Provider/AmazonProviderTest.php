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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WPPack\Component\Security\Bridge\OAuth\Provider\AmazonProvider;

#[CoversClass(AmazonProvider::class)]
final class AmazonProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'amz-client-id',
            clientSecret: 'amz-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsAmazonMetadata(): void
    {
        $def = AmazonProvider::definition();

        self::assertSame('amazon', $def->type);
        self::assertFalse($def->oidc);
        self::assertSame(['profile'], $def->defaultScopes);
    }

    #[Test]
    public function authorizationUrlUsesAmazonEndpointAndOmitsNonce(): void
    {
        $provider = new AmazonProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('s', 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('www.amazon.com', $parsed['host']);
        self::assertSame('/ap/oa', $parsed['path']);
        self::assertSame('profile', $params['scope']);
        self::assertArrayNotHasKey('nonce', $params);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new AmazonProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnAmazonDefaultsAndOidcNulls(): void
    {
        $provider = new AmazonProvider($this->configuration);

        self::assertSame('https://api.amazon.com/auth/o2/token', $provider->getTokenEndpoint());
        self::assertSame('https://api.amazon.com/user/profile', $provider->getUserInfoEndpoint());
        self::assertNull($provider->getJwksUri());
        self::assertNull($provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertFalse($provider->supportsOidc());
    }

    #[Test]
    public function normalizeUserInfoMapsAmazonFieldsToOidcClaims(): void
    {
        $provider = new AmazonProvider($this->configuration);

        $result = $provider->normalizeUserInfo([
            'user_id' => 'amzn1.account.ABC',
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        self::assertSame('amzn1.account.ABC', $result['sub']);
        self::assertSame('Alice', $result['name']);
        self::assertSame('alice@example.com', $result['email']);
    }

    #[Test]
    public function normalizeUserInfoSkipsMissingFields(): void
    {
        $provider = new AmazonProvider($this->configuration);

        self::assertSame([], $provider->normalizeUserInfo([]));
        self::assertSame(['sub' => '1'], $provider->normalizeUserInfo(['user_id' => '1']));
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new AmazonProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }
}
