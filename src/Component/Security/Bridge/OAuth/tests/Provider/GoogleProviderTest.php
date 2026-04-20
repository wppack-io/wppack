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
use WPPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;
use WPPack\Component\Security\Exception\AuthenticationException;

#[CoversClass(GoogleProvider::class)]
final class GoogleProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new OAuthConfiguration(
            clientId: 'google-client-id',
            clientSecret: 'google-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['openid', 'profile', 'email'],
        );
    }

    #[Test]
    public function authorizationUrlIncludesHdWhenHostedDomainIsSet(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('example.com', $params['hd']);
    }

    #[Test]
    public function authorizationUrlExcludesHdWhenNoHostedDomain(): void
    {
        $provider = new GoogleProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertArrayNotHasKey('hd', $params);
    }

    #[Test]
    public function authorizationUrlUsesFirstDomainAsHdHintForArray(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: ['primary.com', 'secondary.com']);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('primary.com', $params['hd']);
    }

    #[Test]
    public function validateHostedDomainPassesForMatchingDomain(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        $this->expectNotToPerformAssertions();
        $provider->validateHostedDomain(['hd' => 'example.com']);
    }

    #[Test]
    public function validateHostedDomainThrowsForMismatchedDomain(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The hosted domain "other.com" is not allowed');

        $provider->validateHostedDomain(['hd' => 'other.com']);
    }

    #[Test]
    public function validateHostedDomainThrowsWhenHdClaimMissing(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('does not contain a "hd" claim');

        $provider->validateHostedDomain(['sub' => '123']);
    }

    #[Test]
    public function validateHostedDomainWithArrayOfDomains(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: ['primary.com', 'secondary.com']);

        $this->expectNotToPerformAssertions();
        $provider->validateHostedDomain(['hd' => 'secondary.com']);
    }

    #[Test]
    public function validateHostedDomainWithArrayRejectsUnlistedDomain(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: ['primary.com', 'secondary.com']);

        $this->expectException(AuthenticationException::class);
        $provider->validateHostedDomain(['hd' => 'unknown.com']);
    }

    #[Test]
    public function validateHostedDomainSkipsWhenNoHostedDomainConfigured(): void
    {
        $provider = new GoogleProvider($this->configuration);

        $this->expectNotToPerformAssertions();
        $provider->validateHostedDomain(['hd' => 'anything.com']);
    }

    #[Test]
    public function defaultEndpoints(): void
    {
        $provider = new GoogleProvider($this->configuration);

        self::assertSame('https://oauth2.googleapis.com/token', $provider->getTokenEndpoint());
        self::assertSame('https://openidconnect.googleapis.com/v1/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://www.googleapis.com/oauth2/v3/certs', $provider->getJwksUri());
        self::assertSame('https://accounts.google.com', $provider->getIssuer());
    }

    #[Test]
    public function defaultAuthorizationEndpoint(): void
    {
        $provider = new GoogleProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
    }

    #[Test]
    public function supportsOidcReturnsTrue(): void
    {
        $provider = new GoogleProvider($this->configuration);

        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function getEndSessionEndpointReturnsNull(): void
    {
        $provider = new GoogleProvider($this->configuration);

        self::assertNull($provider->getEndSessionEndpoint());
    }

    #[Test]
    public function normalizeUserInfoPassesThrough(): void
    {
        $provider = new GoogleProvider($this->configuration);
        $data = ['sub' => '123', 'name' => 'Test', 'email' => 'test@example.com'];

        self::assertSame($data, $provider->normalizeUserInfo($data));
    }

    #[Test]
    public function discoveryUrl(): void
    {
        self::assertSame(
            'https://accounts.google.com/.well-known/openid-configuration',
            GoogleProvider::DISCOVERY_URL,
        );
    }

    #[Test]
    public function authorizationUrlIncludesCodeChallenge(): void
    {
        $provider = new GoogleProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce', 'test-challenge', 'S256');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('test-challenge', $params['code_challenge']);
        self::assertSame('S256', $params['code_challenge_method']);
    }

    #[Test]
    public function authorizationUrlExcludesCodeChallengeWhenNull(): void
    {
        $provider = new GoogleProvider($this->configuration);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertArrayNotHasKey('code_challenge', $params);
        self::assertArrayNotHasKey('code_challenge_method', $params);
    }

    #[Test]
    public function setDiscoveryDocumentOverridesDefaults(): void
    {
        $provider = new GoogleProvider($this->configuration);

        $discoveryDocument = new \WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument(
            issuer: 'https://custom-google.example.com',
            authorizationEndpoint: 'https://custom-google.example.com/authorize',
            tokenEndpoint: 'https://custom-google.example.com/token',
            userinfoEndpoint: 'https://custom-google.example.com/userinfo',
            jwksUri: 'https://custom-google.example.com/keys',
        );

        $provider->setDiscoveryDocument($discoveryDocument);

        self::assertSame('https://custom-google.example.com/token', $provider->getTokenEndpoint());
        self::assertSame('https://custom-google.example.com/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://custom-google.example.com/keys', $provider->getJwksUri());
        self::assertSame('https://custom-google.example.com', $provider->getIssuer());
    }

    #[Test]
    public function authorizationUrlUsesDiscoveryEndpoint(): void
    {
        $discoveryDocument = new \WPPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument(
            issuer: 'https://custom.example.com',
            authorizationEndpoint: 'https://custom.example.com/authorize',
            tokenEndpoint: 'https://custom.example.com/token',
        );

        $provider = new GoogleProvider($this->configuration, discoveryDocument: $discoveryDocument);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith('https://custom.example.com/authorize?', $url);
    }

    #[Test]
    public function configurationEndpointsOverrideDefaults(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'client-id',
            clientSecret: 'client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['openid'],
            authorizationEndpoint: 'https://config.example.com/authorize',
            tokenEndpoint: 'https://config.example.com/token',
            userinfoEndpoint: 'https://config.example.com/userinfo',
            jwksUri: 'https://config.example.com/keys',
            issuer: 'https://config.example.com',
        );

        $provider = new GoogleProvider($config);

        self::assertSame('https://config.example.com/token', $provider->getTokenEndpoint());
        self::assertSame('https://config.example.com/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://config.example.com/keys', $provider->getJwksUri());
        self::assertSame('https://config.example.com', $provider->getIssuer());

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith('https://config.example.com/authorize?', $url);
    }

    #[Test]
    public function definitionReturnsGoogleMetadata(): void
    {
        $def = GoogleProvider::definition();

        self::assertSame('google', $def->type);
        self::assertTrue($def->oidc);
        self::assertContains('hosted_domain', $def->optionalFields);
    }

    #[Test]
    public function validateClaimsDelegatesToHostedDomainCheck(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        // No exception when claim matches allowed hosted domain.
        $this->expectNotToPerformAssertions();
        $provider->validateClaims(['hd' => 'example.com']);
    }

    #[Test]
    public function validateClaimsThrowsWhenHostedDomainMismatch(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('hosted domain');

        $provider->validateClaims(['hd' => 'evil.com']);
    }
}
