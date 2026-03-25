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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;

#[CoversClass(OAuthConfiguration::class)]
final class OAuthConfigurationTest extends TestCase
{
    #[Test]
    public function requiredGetters(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'my-client-id',
            clientSecret: 'my-client-secret',
            redirectUri: 'https://example.com/callback',
        );

        self::assertSame('my-client-id', $config->getClientId());
        self::assertSame('my-client-secret', $config->getClientSecret());
        self::assertSame('https://example.com/callback', $config->getRedirectUri());
    }

    #[Test]
    public function defaultScopes(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
        );

        self::assertSame(['openid', 'email', 'profile'], $config->getScopes());
    }

    #[Test]
    public function customScopes(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['openid', 'custom'],
        );

        self::assertSame(['openid', 'custom'], $config->getScopes());
    }

    #[Test]
    public function optionalEndpointsDefaultToNull(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
        );

        self::assertNull($config->getAuthorizationEndpoint());
        self::assertNull($config->getTokenEndpoint());
        self::assertNull($config->getUserinfoEndpoint());
        self::assertNull($config->getJwksUri());
        self::assertNull($config->getIssuer());
        self::assertNull($config->getDiscoveryUrl());
        self::assertNull($config->getEndSessionEndpoint());
    }

    #[Test]
    public function optionalEndpointsWithValues(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
            authorizationEndpoint: 'https://idp.example.com/authorize',
            tokenEndpoint: 'https://idp.example.com/token',
            userinfoEndpoint: 'https://idp.example.com/userinfo',
            jwksUri: 'https://idp.example.com/.well-known/jwks.json',
            issuer: 'https://idp.example.com',
            discoveryUrl: 'https://idp.example.com/.well-known/openid-configuration',
            endSessionEndpoint: 'https://idp.example.com/logout',
        );

        self::assertSame('https://idp.example.com/authorize', $config->getAuthorizationEndpoint());
        self::assertSame('https://idp.example.com/token', $config->getTokenEndpoint());
        self::assertSame('https://idp.example.com/userinfo', $config->getUserinfoEndpoint());
        self::assertSame('https://idp.example.com/.well-known/jwks.json', $config->getJwksUri());
        self::assertSame('https://idp.example.com', $config->getIssuer());
        self::assertSame('https://idp.example.com/.well-known/openid-configuration', $config->getDiscoveryUrl());
        self::assertSame('https://idp.example.com/logout', $config->getEndSessionEndpoint());
    }

    #[Test]
    public function pkceEnabledByDefault(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
        );

        self::assertTrue($config->isPkceEnabled());
    }

    #[Test]
    public function pkceCanBeDisabled(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
            pkceEnabled: false,
        );

        self::assertFalse($config->isPkceEnabled());
    }
}
