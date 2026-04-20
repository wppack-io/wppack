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
use WPPack\Component\Security\Bridge\OAuth\Provider\CognitoProvider;
use WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(CognitoProvider::class)]
final class CognitoProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'cog-client-id',
            clientSecret: 'cog-client-secret',
            redirectUri: 'https://example.com/callback',
        );
    }

    #[Test]
    public function definitionReturnsCognitoMetadata(): void
    {
        $definition = CognitoProvider::definition();

        self::assertSame('cognito', $definition->type);
        self::assertSame('AWS Cognito', $definition->label);
        self::assertTrue($definition->oidc);
        self::assertContains('domain', $definition->requiredFields);
    }

    #[Test]
    public function endpointsFallBackToCognitoDomainDefaults(): void
    {
        $provider = new CognitoProvider($this->configuration, 'example.auth.us-east-1.amazoncognito.com');

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n');
        $parsed = parse_url($url);

        self::assertSame('/oauth2/authorize', $parsed['path']);
        self::assertStringContainsString('/oauth2/token', $provider->getTokenEndpoint());
        self::assertStringContainsString('/oauth2/userInfo', $provider->getUserInfoEndpoint());
        self::assertStringContainsString('/.well-known/jwks.json', $provider->getJwksUri());
        self::assertSame('https://example.auth.us-east-1.amazoncognito.com', $provider->getIssuer());
        self::assertStringEndsWith('/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function authorizationUrlAppendsPkceWhenChallengeGiven(): void
    {
        $provider = new CognitoProvider($this->configuration, 'example.auth.test');

        $url = $provider->getAuthorizationUrl(state: 's', nonce: 'n', codeChallenge: 'c');
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $params);

        self::assertSame('c', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function discoveryDocumentOverridesAllEndpoints(): void
    {
        $discovery = new DiscoveryDocument(
            issuer: 'https://override.test/',
            authorizationEndpoint: 'https://override.test/authorize',
            tokenEndpoint: 'https://override.test/token',
            userinfoEndpoint: 'https://override.test/userinfo',
            jwksUri: 'https://override.test/jwks',
            endSessionEndpoint: 'https://override.test/logout',
        );
        $provider = new CognitoProvider($this->configuration, 'example.auth.test', $discovery);

        self::assertStringStartsWith('https://override.test/authorize?', $provider->getAuthorizationUrl('s', 'n'));
        self::assertSame('https://override.test/token', $provider->getTokenEndpoint());
        self::assertSame('https://override.test/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://override.test/jwks', $provider->getJwksUri());
        self::assertSame('https://override.test/', $provider->getIssuer());
        self::assertSame('https://override.test/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function discoveryUrlDerivedFromDomain(): void
    {
        $provider = new CognitoProvider($this->configuration, 'tenant.auth.test');

        self::assertSame('https://tenant.auth.test/.well-known/openid-configuration', $provider->getDiscoveryUrl());
    }

    #[Test]
    public function normalizeUserInfoPassthroughAndOidcTrue(): void
    {
        $provider = new CognitoProvider($this->configuration, 'tenant.auth.test');

        self::assertSame(['x' => 1], $provider->normalizeUserInfo(['x' => 1]));
        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function validateClaimsIsNoop(): void
    {
        $provider = new CognitoProvider($this->configuration, 'tenant.auth.test');

        $this->expectNotToPerformAssertions();
        $provider->validateClaims(['sub' => 'u1']);
    }

    #[Test]
    public function setDiscoveryDocumentSwitchesEndpoints(): void
    {
        $provider = new CognitoProvider($this->configuration, 'tenant.auth.test');
        $provider->setDiscoveryDocument(new DiscoveryDocument(
            issuer: 'https://d.test/',
            authorizationEndpoint: 'https://d.test/a',
            tokenEndpoint: 'https://d.test/t',
        ));

        self::assertSame('https://d.test/t', $provider->getTokenEndpoint());
    }
}
