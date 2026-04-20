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
use WPPack\Component\Security\Bridge\OAuth\Provider\OneLoginProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(OneLoginProvider::class)]
final class OneLoginProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'ol-client-id',
            clientSecret: 'ol-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsOneLoginMetadata(): void
    {
        $def = OneLoginProvider::definition();

        self::assertSame('onelogin', $def->type);
        self::assertTrue($def->oidc);
        self::assertContains('domain', $def->requiredFields);
    }

    #[Test]
    public function discoveryUrlIncludesDomain(): void
    {
        $provider = new OneLoginProvider($this->configuration, 'example.onelogin.com');

        self::assertSame(
            'https://example.onelogin.com/oidc/2/.well-known/openid-configuration',
            $provider->getDiscoveryUrl(),
        );
    }

    #[Test]
    public function endpointsFallBackToOneLoginOidcV2Urls(): void
    {
        $provider = new OneLoginProvider($this->configuration, 'example.onelogin.com');

        $url = $provider->getAuthorizationUrl('s', 'n');
        $parsed = parse_url($url);

        self::assertSame('/oidc/2/auth', $parsed['path']);
        self::assertSame('https://example.onelogin.com/oidc/2/token', $provider->getTokenEndpoint());
        self::assertSame('https://example.onelogin.com/oidc/2/me', $provider->getUserInfoEndpoint());
        self::assertSame('https://example.onelogin.com/oidc/2/certs', $provider->getJwksUri());
        self::assertSame('https://example.onelogin.com/oidc/2', $provider->getIssuer());
        self::assertSame('https://example.onelogin.com/oidc/2/logout', $provider->getEndSessionEndpoint());
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new OneLoginProvider($this->configuration, 'example.onelogin.com');

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
            jwksUri: 'https://override.test/jwks',
            endSessionEndpoint: 'https://override.test/logout',
        );

        $provider = new OneLoginProvider($this->configuration, 'example.onelogin.com', $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://override.test/jwks', $provider->getJwksUri());
        self::assertSame('https://override.test/', $provider->getIssuer());
        self::assertSame('https://override.test/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function normalizeUserInfoPassthroughAndValidateClaimsNoop(): void
    {
        $provider = new OneLoginProvider($this->configuration, 'example.onelogin.com');

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
        $provider->validateClaims([]);
        self::assertTrue(true);
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new OneLoginProvider($this->configuration, 'example.onelogin.com');
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }
}
