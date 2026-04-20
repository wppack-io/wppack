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
use WPPack\Component\Security\Bridge\OAuth\Provider\LineProvider;

#[CoversClass(LineProvider::class)]
final class LineProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'line-client-id',
            clientSecret: 'line-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsLineMetadata(): void
    {
        $def = LineProvider::definition();

        self::assertSame('line', $def->type);
        self::assertTrue($def->oidc);
    }

    #[Test]
    public function authorizationUrlUsesLineEndpointWithDefaultScopes(): void
    {
        $provider = new LineProvider($this->configuration);

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('access.line.me', $parsed['host']);
        self::assertSame('/oauth2/v2.1/authorize', $parsed['path']);
        self::assertSame('openid profile email', $params['scope']);
        self::assertSame('s', $params['state']);
        self::assertSame('n', $params['nonce']);
    }

    #[Test]
    public function authorizationUrlAppendsPkceWhenChallengeGiven(): void
    {
        $provider = new LineProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnLineDefaults(): void
    {
        $provider = new LineProvider($this->configuration);

        self::assertSame('https://api.line.me/oauth2/v2.1/token', $provider->getTokenEndpoint());
        self::assertSame('https://api.line.me/v2/profile', $provider->getUserInfoEndpoint());
        self::assertSame('https://api.line.me/oauth2/v2.1/certs', $provider->getJwksUri());
        self::assertSame('https://access.line.me', $provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function normalizeUserInfoMapsLineFieldsToOidcClaims(): void
    {
        $provider = new LineProvider($this->configuration);

        $result = $provider->normalizeUserInfo([
            'userId' => 'U123',
            'displayName' => 'Alice',
            'pictureUrl' => 'https://profile.line-scdn.net/alice.jpg',
            'email' => 'alice@line.me',
        ]);

        self::assertSame('U123', $result['sub']);
        self::assertSame('Alice', $result['name']);
        self::assertSame('https://profile.line-scdn.net/alice.jpg', $result['picture']);
        self::assertSame('alice@line.me', $result['email']);
    }

    #[Test]
    public function normalizeUserInfoSkipsMissingFields(): void
    {
        $provider = new LineProvider($this->configuration);

        $result = $provider->normalizeUserInfo(['userId' => 'only-id']);

        self::assertSame(['sub' => 'only-id'], $result);
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new LineProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }
}
