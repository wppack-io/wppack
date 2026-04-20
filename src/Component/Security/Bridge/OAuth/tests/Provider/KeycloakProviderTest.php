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
use WPPack\Component\Security\Bridge\OAuth\Provider\KeycloakProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(KeycloakProvider::class)]
final class KeycloakProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'kc-client-id',
            clientSecret: 'kc-client-secret',
            redirectUri: 'https://example.com/callback',
        );
    }

    #[Test]
    public function definitionReturnsKeycloakMetadata(): void
    {
        $def = KeycloakProvider::definition();

        self::assertSame('keycloak', $def->type);
        self::assertTrue($def->oidc);
        self::assertContains('domain', $def->requiredFields);
    }

    #[Test]
    public function endpointsFallBackToKeycloakRealmDefaults(): void
    {
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/myrealm');

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        $parsed = parse_url($url);

        self::assertStringContainsString('/protocol/openid-connect/auth', $parsed['path']);
        self::assertStringContainsString('/protocol/openid-connect/token', $provider->getTokenEndpoint());
        self::assertStringContainsString('/protocol/openid-connect/userinfo', $provider->getUserInfoEndpoint());
        self::assertStringContainsString('/protocol/openid-connect/certs', $provider->getJwksUri());
        self::assertSame('https://example.test/realms/myrealm', $provider->getIssuer());
        self::assertStringContainsString('/protocol/openid-connect/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/myrealm');

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n', codeChallenge: 'c');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function discoveryDocumentOverridesEndpoints(): void
    {
        $discovery = new DiscoveryDocument(
            issuer: 'https://override.test/',
            authorizationEndpoint: 'https://override.test/authorize',
            tokenEndpoint: 'https://override.test/token',
            userinfoEndpoint: 'https://override.test/userinfo',
            jwksUri: 'https://override.test/jwks',
            endSessionEndpoint: 'https://override.test/logout',
        );
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/r', $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/', $provider->getIssuer());
    }

    #[Test]
    public function discoveryUrlIncludesRealmPath(): void
    {
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/r');

        self::assertSame('https://example.test/realms/r/.well-known/openid-configuration', $provider->getDiscoveryUrl());
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/r');
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }

    #[Test]
    public function normalizeUserInfoPassthroughAndOidcTrue(): void
    {
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/r');

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new KeycloakProvider($this->configuration, 'example.test/realms/r');

        $this->expectNotToPerformAssertions();
        $provider->validateClaims(['sub' => 'u']);
    }
}
