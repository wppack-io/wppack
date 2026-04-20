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
use WPPack\Component\Security\Bridge\OAuth\Provider\SlackProvider;

#[CoversClass(SlackProvider::class)]
final class SlackProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'slack-client-id',
            clientSecret: 'slack-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsSlackMetadata(): void
    {
        $def = SlackProvider::definition();

        self::assertSame('slack', $def->type);
        self::assertTrue($def->oidc);
    }

    #[Test]
    public function authorizationUrlUsesSlackEndpointWithDefaultScopes(): void
    {
        $provider = new SlackProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('s', 'n');
        $parsed = parse_url($url);
        parse_str((string) ($parsed['query'] ?? ''), $params);

        self::assertSame('slack.com', $parsed['host']);
        self::assertSame('/openid/connect/authorize', $parsed['path']);
        self::assertSame('openid email profile', $params['scope']);
        self::assertSame('n', $params['nonce']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new SlackProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsReturnSlackDefaults(): void
    {
        $provider = new SlackProvider($this->configuration);

        self::assertSame('https://slack.com/api/openid.connect.token', $provider->getTokenEndpoint());
        self::assertSame('https://slack.com/api/openid.connect.userInfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://slack.com/openid/connect/keys', $provider->getJwksUri());
        self::assertSame('https://slack.com', $provider->getIssuer());
        self::assertNull($provider->getEndSessionEndpoint());
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function normalizeUserInfoPassthrough(): void
    {
        $provider = new SlackProvider($this->configuration);

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new SlackProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateClaims([]);
    }
}
