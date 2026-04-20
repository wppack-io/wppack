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
use WPPack\Component\Security\Bridge\OAuth\Provider\AppleProvider;

#[CoversClass(AppleProvider::class)]
final class AppleProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'apple-client-id',
            clientSecret: 'apple-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsAppleMetadata(): void
    {
        $def = AppleProvider::definition();

        self::assertSame('apple', $def->type);
        self::assertTrue($def->oidc);
        self::assertSame(['openid', 'email', 'name'], $def->defaultScopes);
    }

    #[Test]
    public function authorizationUrlUsesAppleIdEndpointAndResponseModeFormPost(): void
    {
        $provider = new AppleProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('s', 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('appleid.apple.com', $parsed['host']);
        self::assertSame('/auth/authorize', $parsed['path']);
        self::assertSame('form_post', $params['response_mode']);
        self::assertSame('openid email name', $params['scope']);
        self::assertSame('n', $params['nonce']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new AppleProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnAppleDefaultsWithNoUserInfo(): void
    {
        $provider = new AppleProvider($this->configuration);

        self::assertSame('https://appleid.apple.com/auth/token', $provider->getTokenEndpoint());
        self::assertNull($provider->getUserInfoEndpoint());
        self::assertSame('https://appleid.apple.com/auth/keys', $provider->getJwksUri());
        self::assertSame('https://appleid.apple.com', $provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function normalizeUserInfoPassthrough(): void
    {
        $provider = new AppleProvider($this->configuration);

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new AppleProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }
}
