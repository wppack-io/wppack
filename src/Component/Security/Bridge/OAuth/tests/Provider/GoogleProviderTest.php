<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Provider\GoogleProvider;

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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The hosted domain "other.com" is not allowed');

        $provider->validateHostedDomain(['hd' => 'other.com']);
    }

    #[Test]
    public function validateHostedDomainThrowsWhenHdClaimMissing(): void
    {
        $provider = new GoogleProvider($this->configuration, hostedDomain: 'example.com');

        $this->expectException(\InvalidArgumentException::class);
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

        $this->expectException(\InvalidArgumentException::class);
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
}
