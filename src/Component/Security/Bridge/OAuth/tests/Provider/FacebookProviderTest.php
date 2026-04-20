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
use WPPack\Component\Security\Bridge\OAuth\Provider\FacebookProvider;

#[CoversClass(FacebookProvider::class)]
final class FacebookProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'fb-client-id',
            clientSecret: 'fb-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsFacebookMetadata(): void
    {
        $def = FacebookProvider::definition();

        self::assertSame('facebook', $def->type);
        self::assertFalse($def->oidc);
        self::assertSame(['email', 'public_profile'], $def->defaultScopes);
    }

    #[Test]
    public function authorizationUrlUsesFacebookOAuthEndpointWithCommaSeparatedScopes(): void
    {
        $provider = new FacebookProvider($this->configuration);

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('www.facebook.com', $parsed['host']);
        self::assertSame('/v21.0/dialog/oauth', $parsed['path']);
        // Facebook expects comma-separated scopes rather than space-separated.
        self::assertSame('email,public_profile', $params['scope']);
        self::assertArrayNotHasKey('nonce', $params);
        self::assertArrayNotHasKey('code_challenge', $params);
    }

    #[Test]
    public function authorizationUrlUsesConfiguredScopesWhenProvided(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['email', 'user_friends'],
        );
        $provider = new FacebookProvider($config);

        parse_str((string) parse_url($provider->getAuthorizationUrl('s', 'n'), \PHP_URL_QUERY), $params);

        self::assertSame('email,user_friends', $params['scope']);
    }

    #[Test]
    public function endpointsReturnFacebookGraphDefaults(): void
    {
        $provider = new FacebookProvider($this->configuration);

        self::assertSame('https://graph.facebook.com/v21.0/oauth/access_token', $provider->getTokenEndpoint());
        self::assertStringStartsWith('https://graph.facebook.com/v21.0/me', $provider->getUserInfoEndpoint());
        self::assertNull($provider->getJwksUri());
        self::assertNull($provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertFalse($provider->supportsOidc());
    }

    #[Test]
    public function normalizeUserInfoMapsFacebookFieldsToOidcClaims(): void
    {
        $provider = new FacebookProvider($this->configuration);

        $result = $provider->normalizeUserInfo([
            'id' => '10001',
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'picture' => ['data' => ['url' => 'https://cdn.facebook.test/alice.jpg']],
        ]);

        self::assertSame('10001', $result['sub']);
        self::assertSame('Alice Example', $result['name']);
        self::assertSame('alice@example.com', $result['email']);
        self::assertSame('https://cdn.facebook.test/alice.jpg', $result['picture']);
    }

    #[Test]
    public function normalizeUserInfoSkipsMissingFields(): void
    {
        $provider = new FacebookProvider($this->configuration);

        $result = $provider->normalizeUserInfo(['id' => '42']);

        self::assertSame('42', $result['sub']);
        self::assertArrayNotHasKey('name', $result);
        self::assertArrayNotHasKey('email', $result);
        self::assertArrayNotHasKey('picture', $result);
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new FacebookProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }
}
