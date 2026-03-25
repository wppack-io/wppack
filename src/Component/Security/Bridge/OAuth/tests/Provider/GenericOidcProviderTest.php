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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Provider\GenericOidcProvider;
use WpPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(GenericOidcProvider::class)]
final class GenericOidcProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['openid', 'profile', 'email'],
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
            userinfoEndpoint: 'https://idp.example.com/userinfo',
            jwksUri: 'https://idp.example.com/.well-known/jwks.json',
            issuer: 'https://idp.example.com',
            endSessionEndpoint: 'https://idp.example.com/logout',
        );
    }

    #[Test]
    public function getAuthorizationUrlBuildsCorrectUrl(): void
    {
        $provider = new GenericOidcProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('https', $parsed['scheme']);
        self::assertSame('idp.example.com', $parsed['host']);
        self::assertSame('/authorize', $parsed['path']);
        self::assertSame('test-client-id', $params['client_id']);
        self::assertSame('https://example.com/callback', $params['redirect_uri']);
        self::assertSame('code', $params['response_type']);
        self::assertSame('openid profile email', $params['scope']);
        self::assertSame('test-state', $params['state']);
        self::assertSame('test-nonce', $params['nonce']);
        self::assertArrayNotHasKey('code_challenge', $params);
    }

    #[Test]
    public function getAuthorizationUrlWithPkce(): void
    {
        $provider = new GenericOidcProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('state', 'nonce', 'challenge123', 'S256');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('challenge123', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointGettersFromConfiguration(): void
    {
        $provider = new GenericOidcProvider($this->configuration);

        self::assertSame('https://idp.example.com/token', $provider->getTokenEndpoint());
        self::assertSame('https://idp.example.com/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://idp.example.com/.well-known/jwks.json', $provider->getJwksUri());
        self::assertSame('https://idp.example.com', $provider->getIssuer());
        self::assertSame('https://idp.example.com/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function endpointGettersFromDiscoveryDocumentOverride(): void
    {
        $discoveryDocument = new DiscoveryDocument(
            issuer: 'https://discovered.example.com',
            authorizationEndpoint: 'https://discovered.example.com/authorize',
            tokenEndpoint: 'https://discovered.example.com/token',
            userinfoEndpoint: 'https://discovered.example.com/userinfo',
            jwksUri: 'https://discovered.example.com/jwks',
            endSessionEndpoint: 'https://discovered.example.com/logout',
        );

        $provider = new GenericOidcProvider($this->configuration, $discoveryDocument);

        self::assertSame('https://discovered.example.com/token', $provider->getTokenEndpoint());
        self::assertSame('https://discovered.example.com/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://discovered.example.com/jwks', $provider->getJwksUri());
        self::assertSame('https://discovered.example.com', $provider->getIssuer());
        self::assertSame('https://discovered.example.com/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function setDiscoveryDocumentOverridesConfiguration(): void
    {
        $provider = new GenericOidcProvider($this->configuration);

        self::assertSame('https://idp.example.com/token', $provider->getTokenEndpoint());

        $discoveryDocument = new DiscoveryDocument(
            issuer: 'https://discovered.example.com',
            authorizationEndpoint: 'https://discovered.example.com/authorize',
            tokenEndpoint: 'https://discovered.example.com/token',
        );

        $provider->setDiscoveryDocument($discoveryDocument);

        self::assertSame('https://discovered.example.com/token', $provider->getTokenEndpoint());
    }

    #[Test]
    public function authorizationUrlUsesDiscoveryEndpoint(): void
    {
        $discoveryDocument = new DiscoveryDocument(
            issuer: 'https://discovered.example.com',
            authorizationEndpoint: 'https://discovered.example.com/auth',
            tokenEndpoint: 'https://discovered.example.com/token',
        );

        $provider = new GenericOidcProvider($this->configuration, $discoveryDocument);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith('https://discovered.example.com/auth?', $url);
    }

    #[Test]
    public function normalizeUserInfoPassesThrough(): void
    {
        $provider = new GenericOidcProvider($this->configuration);
        $data = ['sub' => '123', 'name' => 'Test User', 'email' => 'test@example.com'];

        self::assertSame($data, $provider->normalizeUserInfo($data));
    }

    #[Test]
    public function supportsOidcReturnsTrue(): void
    {
        $provider = new GenericOidcProvider($this->configuration);

        self::assertTrue($provider->supportsOidc());
    }
}
