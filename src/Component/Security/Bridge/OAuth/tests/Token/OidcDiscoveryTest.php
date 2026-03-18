<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\Security\Bridge\OAuth\Token\DiscoveryDocument;
use WpPack\Component\Security\Bridge\OAuth\Token\OidcDiscovery;

#[CoversClass(OidcDiscovery::class)]
final class OidcDiscoveryTest extends TestCase
{
    private HttpClient $httpClient;
    private OidcDiscovery $discovery;

    /** @var array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null */
    private ?array $mockResponse = null;

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
        $this->discovery = new OidcDiscovery($this->httpClient);

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);

        // Clean up transient
        $cacheKey = '_wppack_oidc_discovery_' . md5('https://idp.example.com/.well-known/openid-configuration');
        delete_transient($cacheKey);
    }

    /**
     * @param false|array<string, mixed> $response
     * @param array<string, mixed> $parsedArgs
     */
    public function mockHttpResponse(mixed $response, array $parsedArgs, string $url): mixed
    {
        if ($this->mockResponse !== null) {
            return $this->mockResponse;
        }

        return $response;
    }

    #[Test]
    public function discoverReturnsDocument(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'issuer' => 'https://idp.example.com',
                'authorization_endpoint' => 'https://idp.example.com/authorize',
                'token_endpoint' => 'https://idp.example.com/token',
                'userinfo_endpoint' => 'https://idp.example.com/userinfo',
                'jwks_uri' => 'https://idp.example.com/.well-known/jwks.json',
                'end_session_endpoint' => 'https://idp.example.com/logout',
            ]),
        ];

        $document = $this->discovery->discover(
            'https://idp.example.com/.well-known/openid-configuration',
        );

        self::assertSame('https://idp.example.com', $document->getIssuer());
        self::assertSame('https://idp.example.com/authorize', $document->getAuthorizationEndpoint());
        self::assertSame('https://idp.example.com/token', $document->getTokenEndpoint());
        self::assertSame('https://idp.example.com/userinfo', $document->getUserinfoEndpoint());
        self::assertSame('https://idp.example.com/.well-known/jwks.json', $document->getJwksUri());
        self::assertSame('https://idp.example.com/logout', $document->getEndSessionEndpoint());
    }

    #[Test]
    public function discoverCachesInTransient(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'issuer' => 'https://idp.example.com',
                'authorization_endpoint' => 'https://idp.example.com/authorize',
                'token_endpoint' => 'https://idp.example.com/token',
            ]),
        ];

        $this->discovery->discover(
            'https://idp.example.com/.well-known/openid-configuration',
        );

        $cacheKey = '_wppack_oidc_discovery_' . md5('https://idp.example.com/.well-known/openid-configuration');
        $cached = get_transient($cacheKey);

        self::assertIsArray($cached);
        self::assertSame('https://idp.example.com', $cached['issuer']);
    }

    #[Test]
    public function discoverUsesTransientCache(): void
    {
        $cacheKey = '_wppack_oidc_discovery_' . md5('https://idp.example.com/.well-known/openid-configuration');
        set_transient($cacheKey, [
            'issuer' => 'https://cached.example.com',
            'authorization_endpoint' => 'https://cached.example.com/authorize',
            'token_endpoint' => 'https://cached.example.com/token',
        ], 86400);

        // No mock response set — HTTP call would fail if made
        $document = $this->discovery->discover(
            'https://idp.example.com/.well-known/openid-configuration',
        );

        self::assertSame('https://cached.example.com', $document->getIssuer());
    }

    #[Test]
    public function discoverThrowsOnHttpError(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 404, 'message' => 'Not Found'],
            'headers' => [],
            'body' => 'Not Found',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OIDC discovery failed.');

        $this->discovery->discover(
            'https://idp.example.com/.well-known/openid-configuration',
        );
    }

    #[Test]
    public function discoverThrowsOnInvalidJson(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => 'not-json',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OIDC discovery returned invalid JSON.');

        $this->discovery->discover(
            'https://idp.example.com/.well-known/openid-configuration',
        );
    }

    #[Test]
    public function discoverThrowsForNonHttpsUrl(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Discovery URL must use HTTPS.');

        $this->discovery->discover('http://idp.example.com/.well-known/openid-configuration');
    }
}
