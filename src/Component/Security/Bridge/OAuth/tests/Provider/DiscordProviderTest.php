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
use WPPack\Component\Security\Bridge\OAuth\Provider\DiscordProvider;

#[CoversClass(DiscordProvider::class)]
final class DiscordProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'd-client-id',
            clientSecret: 'd-client-secret',
            redirectUri: 'https://example.com/callback',
        );
    }

    #[Test]
    public function definitionReturnsDiscordMetadata(): void
    {
        $definition = DiscordProvider::definition();

        self::assertSame('discord', $definition->type);
        self::assertFalse($definition->oidc);
        self::assertSame(['identify', 'email'], $definition->defaultScopes);
    }

    #[Test]
    public function authorizationUrlUsesDiscordEndpointAndOmitsNonce(): void
    {
        $provider = new DiscordProvider($this->configuration);

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('discord.com', $parsed['host']);
        self::assertSame('d-client-id', $params['client_id']);
        self::assertSame('s', $params['state']);
        // Discord is OAuth2 only — the nonce parameter is not forwarded.
        self::assertArrayNotHasKey('nonce', $params);
    }

    #[Test]
    public function authorizationUrlFallsBackToDiscordDefaultScopesWhenConfigHasNone(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );

        $provider = new DiscordProvider($config);

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertSame('identify email', $params['scope']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new DiscordProvider($this->configuration);

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n', codeChallenge: 'c');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnDiscordDefaultsAndOidcNulls(): void
    {
        $provider = new DiscordProvider($this->configuration);

        self::assertSame('https://discord.com/api/oauth2/token', $provider->getTokenEndpoint());
        self::assertSame('https://discord.com/api/users/@me', $provider->getUserInfoEndpoint());
        self::assertNull($provider->getJwksUri());
        self::assertNull($provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertFalse($provider->supportsOidc());
    }

    #[Test]
    public function normalizeUserInfoMapsDiscordFieldsToOidcClaims(): void
    {
        $provider = new DiscordProvider($this->configuration);

        $result = $provider->normalizeUserInfo([
            'id' => '123',
            'username' => 'alice',
            'global_name' => 'Alice Example',
            'email' => 'alice@example.com',
            'avatar' => 'abc',
        ]);

        self::assertSame('123', $result['sub']);
        self::assertSame('alice', $result['preferred_username']);
        self::assertSame('Alice Example', $result['name']);
        self::assertSame('alice@example.com', $result['email']);
        self::assertSame('https://cdn.discordapp.com/avatars/123/abc.png', $result['picture']);
    }

    #[Test]
    public function normalizeUserInfoSkipsMissingFields(): void
    {
        $provider = new DiscordProvider($this->configuration);

        $result = $provider->normalizeUserInfo(['id' => '1']);

        self::assertSame('1', $result['sub']);
        self::assertArrayNotHasKey('preferred_username', $result);
        self::assertArrayNotHasKey('email', $result);
        self::assertArrayNotHasKey('picture', $result);
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new DiscordProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims(['sub' => 'u']);
    }
}
