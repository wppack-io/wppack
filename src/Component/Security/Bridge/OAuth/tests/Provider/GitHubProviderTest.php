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

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Provider\GitHubProvider;

#[CoversClass(GitHubProvider::class)]
final class GitHubProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'github-client-id',
            clientSecret: 'github-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['read:user', 'user:email'],
        );
    }

    #[Test]
    public function authorizationUrlOnlyHasClientIdRedirectUriScopeState(): void
    {
        $provider = new GitHubProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('github-client-id', $params['client_id']);
        self::assertSame('https://example.com/callback', $params['redirect_uri']);
        self::assertSame('read:user user:email', $params['scope']);
        self::assertSame('test-state', $params['state']);

        self::assertCount(4, $params);
    }

    #[Test]
    public function authorizationUrlDoesNotIncludeNonce(): void
    {
        $provider = new GitHubProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertArrayNotHasKey('nonce', $params);
    }

    #[Test]
    public function authorizationUrlDoesNotIncludePkce(): void
    {
        $provider = new GitHubProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('state', 'nonce', 'challenge', 'S256');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertArrayNotHasKey('code_challenge', $params);
        self::assertArrayNotHasKey('code_challenge_method', $params);
    }

    #[Test]
    public function authorizationUrlDefaultEndpoint(): void
    {
        $provider = new GitHubProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith('https://github.com/login/oauth/authorize?', $url);
    }

    #[Test]
    public function normalizeUserInfoMapping(): void
    {
        $provider = new GitHubProvider($this->configuration);

        $data = [
            'id' => 12345,
            'login' => 'octocat',
            'name' => 'The Octocat',
            'email' => 'octocat@github.com',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
            'bio' => 'Some bio text',
        ];

        $normalized = $provider->normalizeUserInfo($data);

        self::assertSame('12345', $normalized['sub']);
        self::assertSame('octocat', $normalized['preferred_username']);
        self::assertSame('The Octocat', $normalized['name']);
        self::assertSame('octocat@github.com', $normalized['email']);
        self::assertSame('https://avatars.githubusercontent.com/u/12345', $normalized['picture']);
        self::assertArrayNotHasKey('bio', $normalized);
        self::assertArrayNotHasKey('id', $normalized);
        self::assertArrayNotHasKey('login', $normalized);
        self::assertArrayNotHasKey('avatar_url', $normalized);
    }

    #[Test]
    public function normalizeUserInfoWithPartialData(): void
    {
        $provider = new GitHubProvider($this->configuration);

        $data = ['id' => 999, 'login' => 'testuser'];
        $normalized = $provider->normalizeUserInfo($data);

        self::assertSame('999', $normalized['sub']);
        self::assertSame('testuser', $normalized['preferred_username']);
        self::assertArrayNotHasKey('name', $normalized);
        self::assertArrayNotHasKey('email', $normalized);
        self::assertArrayNotHasKey('picture', $normalized);
    }

    #[Test]
    public function normalizeUserInfoWithEmptyData(): void
    {
        $provider = new GitHubProvider($this->configuration);

        self::assertSame([], $provider->normalizeUserInfo([]));
    }

    #[Test]
    public function supportsOidcReturnsFalse(): void
    {
        $provider = new GitHubProvider($this->configuration);

        self::assertFalse($provider->supportsOidc());
    }

    #[Test]
    public function getJwksUriReturnsNull(): void
    {
        $provider = new GitHubProvider($this->configuration);

        self::assertNull($provider->getJwksUri());
    }

    #[Test]
    public function getIssuerReturnsNull(): void
    {
        $provider = new GitHubProvider($this->configuration);

        self::assertNull($provider->getIssuer());
    }

    #[Test]
    public function getEndSessionEndpointReturnsNull(): void
    {
        $provider = new GitHubProvider($this->configuration);

        self::assertNull($provider->getEndSessionEndpoint());
    }

    #[Test]
    public function defaultEndpoints(): void
    {
        $provider = new GitHubProvider($this->configuration);

        self::assertSame('https://github.com/login/oauth/access_token', $provider->getTokenEndpoint());
        self::assertSame('https://api.github.com/user', $provider->getUserInfoEndpoint());
    }
}
