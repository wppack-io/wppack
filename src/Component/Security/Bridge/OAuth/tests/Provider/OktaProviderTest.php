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
use WPPack\Component\Security\Bridge\OAuth\Provider\OktaProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(OktaProvider::class)]
final class OktaProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'okta-client-id',
            clientSecret: 'okta-client-secret',
            redirectUri: 'https://example.com/callback',
        );
    }

    #[Test]
    public function definitionReturnsOktaMetadata(): void
    {
        $def = OktaProvider::definition();

        self::assertSame('okta', $def->type);
        self::assertTrue($def->oidc);
        self::assertContains('domain', $def->requiredFields);
    }

    #[Test]
    public function endpointsFallBackToOktaDefaultAuthServer(): void
    {
        $provider = new OktaProvider($this->configuration, 'tenant.okta.com');

        $parsed = parse_url($provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('/oauth2/default/v1/authorize', $parsed['path']);
        self::assertStringEndsWith('/oauth2/default/v1/token', $provider->getTokenEndpoint());
        self::assertStringEndsWith('/oauth2/default/v1/userinfo', $provider->getUserInfoEndpoint());
        self::assertStringEndsWith('/oauth2/default/v1/keys', $provider->getJwksUri());
        self::assertSame('https://tenant.okta.com/oauth2/default', $provider->getIssuer());
        self::assertStringEndsWith('/oauth2/default/v1/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new OktaProvider($this->configuration, 'tenant.okta.com');

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

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
            jwksUri: 'https://override.test/keys',
            endSessionEndpoint: 'https://override.test/logout',
        );

        $provider = new OktaProvider($this->configuration, 'tenant.okta.com', $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/keys', $provider->getJwksUri());
        self::assertSame('https://override.test/', $provider->getIssuer());
        self::assertSame('https://override.test/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function discoveryUrlIncludesTenantDomain(): void
    {
        $provider = new OktaProvider($this->configuration, 'tenant.okta.com');

        self::assertSame('https://tenant.okta.com/.well-known/openid-configuration', $provider->getDiscoveryUrl());
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new OktaProvider($this->configuration, 'tenant.okta.com');
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
        $provider = new OktaProvider($this->configuration, 'tenant.okta.com');

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new OktaProvider($this->configuration, 'tenant.okta.com');

        $this->expectNotToPerformAssertions();
        $provider->validateClaims(['sub' => 'u']);
    }
}
