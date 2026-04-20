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
use WPPack\Component\Security\Bridge\OAuth\Provider\EntraIdProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(EntraIdProvider::class)]
final class EntraIdProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'e-client-id',
            clientSecret: 'e-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: [],
        );
    }

    #[Test]
    public function definitionReturnsEntraIdMetadata(): void
    {
        $def = EntraIdProvider::definition();

        self::assertSame('entra-id', $def->type);
        self::assertTrue($def->oidc);
        self::assertContains('tenant_id', $def->requiredFields);
    }

    #[Test]
    public function constructorRejectsInvalidTenantId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Azure tenant ID');

        new EntraIdProvider($this->configuration, 'not-a-valid-tenant');
    }

    #[Test]
    public function constructorAcceptsGuidTenantId(): void
    {
        $provider = new EntraIdProvider($this->configuration, '11111111-2222-3333-4444-555555555555');

        self::assertStringContainsString('11111111-2222-3333-4444-555555555555', $provider->getDiscoveryUrl());
    }

    #[Test]
    public function constructorAcceptsCommonAliases(): void
    {
        foreach (['common', 'organizations', 'consumers'] as $alias) {
            $provider = new EntraIdProvider($this->configuration, $alias);
            self::assertStringContainsString($alias, $provider->getDiscoveryUrl());
        }
    }

    #[Test]
    public function authorizationUrlUsesTenantSpecificEndpointAndPromptSelectAccount(): void
    {
        $provider = new EntraIdProvider($this->configuration, 'common');

        $url = $provider->getAuthorizationUrl('s', 'n');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', $url);
        self::assertSame('select_account', $params['prompt']);
        self::assertSame('openid email profile', $params['scope']);
    }

    #[Test]
    public function authorizationUrlAddsPkceWhenChallengeGiven(): void
    {
        $provider = new EntraIdProvider($this->configuration, 'common');

        parse_str((string) parse_url(
            $provider->getAuthorizationUrl('s', 'n', codeChallenge: 'c'),
            \PHP_URL_QUERY,
        ), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function endpointsFallBackToTenantSpecificAzureUrls(): void
    {
        $provider = new EntraIdProvider($this->configuration, 'common');

        self::assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/token', $provider->getTokenEndpoint());
        self::assertSame('https://login.microsoftonline.com/common/discovery/v2.0/keys', $provider->getJwksUri());
        self::assertSame('https://login.microsoftonline.com/common/v2.0', $provider->getIssuer());
        self::assertSame('https://login.microsoftonline.com/common/oauth2/v2.0/logout', $provider->getEndSessionEndpoint());
        // Entra ID does not expose a standard userinfo endpoint
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
        $provider = new EntraIdProvider($this->configuration, 'common', $discovery);

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
        $provider = new EntraIdProvider($this->configuration, 'common');

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
        $provider->validateClaims([]);
        self::assertTrue(true);
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new EntraIdProvider($this->configuration, 'common');
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }
}
