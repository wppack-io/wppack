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
use WPPack\Component\Security\Bridge\OAuth\Provider\YahooJapanProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(YahooJapanProvider::class)]
final class YahooJapanProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'yj-client-id',
            clientSecret: 'yj-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsYahooJapanMetadata(): void
    {
        $def = YahooJapanProvider::definition();

        self::assertSame('yahoo-japan', $def->type);
        self::assertTrue($def->oidc);
    }

    #[Test]
    public function discoveryUrlIsConstant(): void
    {
        $provider = new YahooJapanProvider($this->configuration);

        self::assertSame(
            'https://auth.login.yahoo.co.jp/yconnect/v2/.well-known/openid-configuration',
            $provider->getDiscoveryUrl(),
        );
    }

    #[Test]
    public function authorizationUrlUsesYconnectEndpointAndDefaultScopes(): void
    {
        $provider = new YahooJapanProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('s', 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('auth.login.yahoo.co.jp', $parsed['host']);
        self::assertSame('/yconnect/v2/authorization', $parsed['path']);
        self::assertSame('openid email profile', $params['scope']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new YahooJapanProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnYconnectDefaults(): void
    {
        $provider = new YahooJapanProvider($this->configuration);

        self::assertSame('https://auth.login.yahoo.co.jp/yconnect/v2/token', $provider->getTokenEndpoint());
        self::assertSame('https://userinfo.yahooapis.jp/yconnect/v2/attribute', $provider->getUserInfoEndpoint());
        self::assertSame('https://auth.login.yahoo.co.jp/yconnect/v2/jwks', $provider->getJwksUri());
        self::assertSame('https://auth.login.yahoo.co.jp/yconnect/v2', $provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertTrue($provider->supportsOidc());
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
        );
        $provider = new YahooJapanProvider($this->configuration, $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://override.test/jwks', $provider->getJwksUri());
        self::assertSame('https://override.test/', $provider->getIssuer());
    }

    #[Test]
    public function normalizeUserInfoPassthrough(): void
    {
        $provider = new YahooJapanProvider($this->configuration);

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new YahooJapanProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new YahooJapanProvider($this->configuration);
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }
}
