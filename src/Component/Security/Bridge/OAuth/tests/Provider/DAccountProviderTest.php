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
use WPPack\Component\Security\Bridge\OAuth\Provider\DAccountProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(DAccountProvider::class)]
final class DAccountProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'd-client-id',
            clientSecret: 'd-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsDAccountMetadata(): void
    {
        $def = DAccountProvider::definition();

        self::assertSame('d-account', $def->type);
        self::assertTrue($def->oidc);
    }

    #[Test]
    public function discoveryUrlIsConstant(): void
    {
        $provider = new DAccountProvider($this->configuration);

        self::assertSame(
            'https://conf.uw.docomo.ne.jp/.well-known/openid-configuration',
            $provider->getDiscoveryUrl(),
        );
    }

    #[Test]
    public function authorizationUrlUsesDocomoEndpointAndDefaultScopes(): void
    {
        $provider = new DAccountProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('s', 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('id.smt.docomo.ne.jp', $parsed['host']);
        self::assertSame('/cgi8/oidc/authorize', $parsed['path']);
        self::assertSame('openid email profile', $params['scope']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new DAccountProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnDocomoDefaults(): void
    {
        $provider = new DAccountProvider($this->configuration);

        self::assertSame('https://conf.uw.docomo.ne.jp/common/token', $provider->getTokenEndpoint());
        self::assertSame('https://conf.uw.docomo.ne.jp/common/userinfo', $provider->getUserInfoEndpoint());
        // dAccount does not publish a JWKS URI; provider returns empty string.
        self::assertSame('', $provider->getJwksUri());
        self::assertSame('https://conf.uw.docomo.ne.jp/', $provider->getIssuer());
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

        $provider = new DAccountProvider($this->configuration, $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://override.test/jwks', $provider->getJwksUri());
        self::assertSame('https://override.test/', $provider->getIssuer());
    }

    #[Test]
    public function normalizeUserInfoPassthrough(): void
    {
        $provider = new DAccountProvider($this->configuration);

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new DAccountProvider($this->configuration);
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new DAccountProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }
}
