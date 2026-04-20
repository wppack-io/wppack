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
use WPPack\Component\Security\Bridge\OAuth\Provider\Auth0Provider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(Auth0Provider::class)]
final class Auth0ProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'auth0-client-id',
            clientSecret: 'auth0-client-secret',
            redirectUri: 'https://example.com/callback',
        );
    }

    #[Test]
    public function definitionReturnsAuth0Metadata(): void
    {
        $definition = Auth0Provider::definition();

        self::assertSame('auth0', $definition->type);
        self::assertSame('Auth0', $definition->label);
        self::assertTrue($definition->oidc);
        self::assertContains('domain', $definition->requiredFields);
    }

    #[Test]
    public function discoveryUrlIncludesDomain(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');

        self::assertSame(
            'https://tenant.auth0.com/.well-known/openid-configuration',
            $provider->getDiscoveryUrl(),
        );
    }

    #[Test]
    public function authorizationUrlUsesDomainEndpointWhenDiscoveryMissing(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        self::assertSame('tenant.auth0.com', $parsed['host']);
        self::assertSame('/authorize', $parsed['path']);
        self::assertSame('auth0-client-id', $params['client_id']);
        self::assertSame('https://example.com/callback', $params['redirect_uri']);
        self::assertSame('openid email profile', $params['scope']);
        self::assertSame('s', $params['state']);
        self::assertSame('n', $params['nonce']);
        self::assertArrayNotHasKey('code_challenge', $params);
    }

    #[Test]
    public function authorizationUrlIncludesPkceWhenChallengeProvided(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n', codeChallenge: 'abc');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertSame('abc', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function authorizationUrlPrefersDiscoveryDocumentEndpoint(): void
    {
        $discovery = new DiscoveryDocument(
            issuer: 'https://discovered.test/',
            authorizationEndpoint: 'https://discovered.test/oauth/authorize',
            tokenEndpoint: 'https://discovered.test/oauth/token',
            userinfoEndpoint: 'https://discovered.test/userinfo',
            jwksUri: 'https://discovered.test/.well-known/jwks.json',
            endSessionEndpoint: 'https://discovered.test/logout',
        );
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com', $discovery);

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');

        self::assertStringStartsWith('https://discovered.test/oauth/authorize?', $url);
        self::assertSame('https://discovered.test/oauth/token', $provider->getTokenEndpoint());
        self::assertSame('https://discovered.test/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://discovered.test/.well-known/jwks.json', $provider->getJwksUri());
        self::assertSame('https://discovered.test/', $provider->getIssuer());
        self::assertSame('https://discovered.test/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function endpointsFallBackToDomainWhenNoDiscoveryOrConfig(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');

        self::assertSame('https://tenant.auth0.com/oauth/token', $provider->getTokenEndpoint());
        self::assertSame('https://tenant.auth0.com/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://tenant.auth0.com/.well-known/jwks.json', $provider->getJwksUri());
        self::assertSame('https://tenant.auth0.com/', $provider->getIssuer());
        self::assertSame('https://tenant.auth0.com/v2/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');
        $discovery = new DiscoveryDocument(
            issuer: 'https://override.test/',
            authorizationEndpoint: 'https://override.test/authorize',
            tokenEndpoint: 'https://override.test/token',
            userinfoEndpoint: 'https://override.test/userinfo',
            jwksUri: 'https://override.test/jwks',
        );

        $provider->setDiscoveryDocument($discovery);

        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
    }

    #[Test]
    public function normalizeUserInfoReturnsClaimsUntouched(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');
        $claims = ['sub' => 'user|1', 'email' => 'a@b.com'];

        self::assertSame($claims, $provider->normalizeUserInfo($claims));
    }

    #[Test]
    public function supportsOidcReturnsTrue(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');

        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function validateClaimsIsANoOp(): void
    {
        $provider = new Auth0Provider($this->configuration, 'tenant.auth0.com');

        $this->expectNotToPerformAssertions();
        $provider->validateClaims(['sub' => 'u1']);
    }
}
