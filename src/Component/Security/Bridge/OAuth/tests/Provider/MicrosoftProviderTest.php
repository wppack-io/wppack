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
use WPPack\Component\Security\Bridge\OAuth\Provider\MicrosoftProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(MicrosoftProvider::class)]
final class MicrosoftProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'ms-client-id',
            clientSecret: 'ms-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsMicrosoftMetadata(): void
    {
        $def = MicrosoftProvider::definition();

        self::assertSame('microsoft', $def->type);
        self::assertTrue($def->oidc);
    }

    #[Test]
    public function discoveryUrlPinnedToConsumersTenant(): void
    {
        $provider = new MicrosoftProvider($this->configuration);

        self::assertSame(
            'https://login.microsoftonline.com/consumers/v2.0/.well-known/openid-configuration',
            $provider->getDiscoveryUrl(),
        );
    }

    #[Test]
    public function authorizationUrlUsesConsumersTenantAndPromptSelectAccount(): void
    {
        $provider = new MicrosoftProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('s', 'n');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertStringStartsWith('https://login.microsoftonline.com/consumers/oauth2/v2.0/authorize?', $url);
        self::assertSame('select_account', $params['prompt']);
        self::assertSame('openid email profile', $params['scope']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new MicrosoftProvider($this->configuration);

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsFallBackToConsumersTenantUrls(): void
    {
        $provider = new MicrosoftProvider($this->configuration);

        self::assertSame('https://login.microsoftonline.com/consumers/oauth2/v2.0/token', $provider->getTokenEndpoint());
        self::assertSame('https://login.microsoftonline.com/consumers/discovery/v2.0/keys', $provider->getJwksUri());
        self::assertSame('https://login.microsoftonline.com/consumers/v2.0', $provider->getIssuer());
        self::assertSame('https://login.microsoftonline.com/consumers/oauth2/v2.0/logout', $provider->getEndSessionEndpoint());
        self::assertNull($provider->getUserInfoEndpoint());
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
            jwksUri: 'https://override.test/keys',
            endSessionEndpoint: 'https://override.test/logout',
        );
        $provider = new MicrosoftProvider($this->configuration, $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://override.test/keys', $provider->getJwksUri());
        self::assertSame('https://override.test/', $provider->getIssuer());
        self::assertSame('https://override.test/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function normalizeUserInfoPassthroughAndValidateClaimsNoop(): void
    {
        $provider = new MicrosoftProvider($this->configuration);

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
        $provider->validateClaims([]);
        self::assertTrue(true);
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new MicrosoftProvider($this->configuration);
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }
}
