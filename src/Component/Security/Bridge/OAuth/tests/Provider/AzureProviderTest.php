<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Bridge\OAuth\Configuration\OAuthConfiguration;
use WpPack\Component\Security\Bridge\OAuth\Provider\AzureProvider;
use WpPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;

#[CoversClass(AzureProvider::class)]
final class AzureProviderTest extends TestCase
{
    private OAuthConfiguration $configuration;
    private string $tenantId;

    protected function setUp(): void
    {
        $this->tenantId = '550e8400-e29b-41d4-a716-446655440000';
        $this->configuration = new OAuthConfiguration(
            clientId: 'azure-client-id',
            clientSecret: 'azure-client-secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['openid', 'profile', 'email'],
        );
    }

    #[Test]
    public function endpointsContainTenantId(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        self::assertStringContainsString(
            $this->tenantId,
            $provider->getTokenEndpoint(),
        );
        self::assertStringContainsString(
            $this->tenantId,
            $provider->getJwksUri(),
        );
        self::assertStringContainsString(
            $this->tenantId,
            $provider->getIssuer(),
        );
        self::assertStringContainsString(
            $this->tenantId,
            $provider->getEndSessionEndpoint(),
        );
    }

    #[Test]
    public function defaultEndpoints(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        self::assertSame(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/oauth2/v2.0/token',
            $provider->getTokenEndpoint(),
        );
        self::assertSame(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/discovery/v2.0/keys',
            $provider->getJwksUri(),
        );
        self::assertSame(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/v2.0',
            $provider->getIssuer(),
        );
        self::assertSame(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/oauth2/v2.0/logout',
            $provider->getEndSessionEndpoint(),
        );
    }

    #[Test]
    public function authorizationUrlIncludesPromptSelectAccount(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        $url = $provider->getAuthorizationUrl('test-state', 'test-nonce');
        $parsed = parse_url($url);
        parse_str($parsed['query'], $params);

        self::assertSame('select_account', $params['prompt']);
    }

    #[Test]
    public function authorizationUrlDefaultEndpoint(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/oauth2/v2.0/authorize?',
            $url,
        );
    }

    #[Test]
    public function discoveryUrlPattern(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        self::assertSame(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/v2.0/.well-known/openid-configuration',
            $provider->getDiscoveryUrl(),
        );
    }

    #[Test]
    public function supportsOidcReturnsTrue(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        self::assertTrue($provider->supportsOidc());
    }

    #[Test]
    public function getEndSessionEndpointReturnsUrl(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        self::assertNotNull($provider->getEndSessionEndpoint());
        self::assertStringContainsString('logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function setDiscoveryDocumentOverridesDefaults(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);

        self::assertSame(
            'https://login.microsoftonline.com/550e8400-e29b-41d4-a716-446655440000/oauth2/v2.0/token',
            $provider->getTokenEndpoint(),
        );

        $discoveryDocument = new DiscoveryDocument(
            issuer: 'https://custom-issuer.example.com',
            authorizationEndpoint: 'https://custom.example.com/authorize',
            tokenEndpoint: 'https://custom.example.com/token',
            userinfoEndpoint: 'https://custom.example.com/userinfo',
            jwksUri: 'https://custom.example.com/keys',
            endSessionEndpoint: 'https://custom.example.com/logout',
        );

        $provider->setDiscoveryDocument($discoveryDocument);

        self::assertSame('https://custom.example.com/token', $provider->getTokenEndpoint());
        self::assertSame('https://custom.example.com/userinfo', $provider->getUserInfoEndpoint());
        self::assertSame('https://custom.example.com/keys', $provider->getJwksUri());
        self::assertSame('https://custom-issuer.example.com', $provider->getIssuer());
        self::assertSame('https://custom.example.com/logout', $provider->getEndSessionEndpoint());
    }

    #[Test]
    public function authorizationUrlUsesDiscoveryEndpoint(): void
    {
        $discoveryDocument = new DiscoveryDocument(
            issuer: 'https://custom.example.com',
            authorizationEndpoint: 'https://custom.example.com/authorize',
            tokenEndpoint: 'https://custom.example.com/token',
        );

        $provider = new AzureProvider($this->configuration, $this->tenantId, $discoveryDocument);

        $url = $provider->getAuthorizationUrl('state', 'nonce');
        self::assertStringStartsWith('https://custom.example.com/authorize?', $url);
    }

    #[Test]
    public function normalizeUserInfoPassesThrough(): void
    {
        $provider = new AzureProvider($this->configuration, $this->tenantId);
        $data = ['sub' => '123', 'name' => 'Test', 'email' => 'test@example.com'];

        self::assertSame($data, $provider->normalizeUserInfo($data));
    }

    #[Test]
    public function invalidTenantIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Azure tenant ID format.');

        new AzureProvider($this->configuration, '../malicious-path');
    }

    #[Test]
    public function commonTenantIdIsAccepted(): void
    {
        $provider = new AzureProvider($this->configuration, 'common');

        self::assertStringContainsString('common', $provider->getTokenEndpoint());
    }

    #[Test]
    public function organizationsTenantIdIsAccepted(): void
    {
        $provider = new AzureProvider($this->configuration, 'organizations');

        self::assertStringContainsString('organizations', $provider->getTokenEndpoint());
    }

    #[Test]
    public function consumersTenantIdIsAccepted(): void
    {
        $provider = new AzureProvider($this->configuration, 'consumers');

        self::assertStringContainsString('consumers', $provider->getTokenEndpoint());
    }

    #[Test]
    public function getUserInfoEndpointReturnsNullWithoutDiscoveryOrConfig(): void
    {
        $config = new OAuthConfiguration(
            clientId: 'id',
            clientSecret: 'secret',
            redirectUri: 'https://example.com/callback',
            scopes: ['openid'],
        );

        $provider = new AzureProvider($config, $this->tenantId);

        self::assertNull($provider->getUserInfoEndpoint());
    }
}
