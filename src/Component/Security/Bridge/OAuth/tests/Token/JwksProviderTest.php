<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\Security\Bridge\OAuth\Token\JwksProvider;

#[CoversClass(JwksProvider::class)]
final class JwksProviderTest extends TestCase
{
    private HttpClient $httpClient;
    private JwksProvider $provider;

    /** @var array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null */
    private ?array $mockResponse = null;

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
        $this->provider = new JwksProvider($this->httpClient);

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);

        $cacheKey = '_wppack_oauth_jwks_' . md5('https://idp.example.com/.well-known/jwks.json');
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
    public function getKeysReturnsJwksArray(): void
    {
        $jwksKeys = [
            [
                'kty' => 'RSA',
                'kid' => 'key-1',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => 'some-modulus',
                'e' => 'AQAB',
            ],
        ];

        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['keys' => $jwksKeys]),
        ];

        $keys = $this->provider->getKeys('https://idp.example.com/.well-known/jwks.json');

        self::assertCount(1, $keys);
        self::assertSame('key-1', $keys[0]['kid']);
        self::assertSame('RSA', $keys[0]['kty']);
    }

    #[Test]
    public function getKeysCachesInTransient(): void
    {
        $jwksKeys = [
            ['kty' => 'RSA', 'kid' => 'cached-key', 'n' => 'n-value', 'e' => 'AQAB'],
        ];

        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['keys' => $jwksKeys]),
        ];

        $this->provider->getKeys('https://idp.example.com/.well-known/jwks.json');

        $cacheKey = '_wppack_oauth_jwks_' . md5('https://idp.example.com/.well-known/jwks.json');
        $cached = get_transient($cacheKey);

        self::assertIsArray($cached);
        self::assertSame('cached-key', $cached[0]['kid']);
    }

    #[Test]
    public function getKeysUsesTransientCache(): void
    {
        $cachedKeys = [
            ['kty' => 'RSA', 'kid' => 'from-cache', 'n' => 'n-value', 'e' => 'AQAB'],
        ];

        $cacheKey = '_wppack_oauth_jwks_' . md5('https://idp.example.com/.well-known/jwks.json');
        set_transient($cacheKey, $cachedKeys, 3600);

        // No mock response set — HTTP call would fail if made
        $keys = $this->provider->getKeys('https://idp.example.com/.well-known/jwks.json');

        self::assertCount(1, $keys);
        self::assertSame('from-cache', $keys[0]['kid']);
    }

    #[Test]
    public function getKeysThrowsOnHttpError(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 500, 'message' => 'Internal Server Error'],
            'headers' => [],
            'body' => 'Server Error',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWKS fetch failed.');

        $this->provider->getKeys('https://idp.example.com/.well-known/jwks.json');
    }

    #[Test]
    public function getKeysThrowsOnMissingKeysField(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['not_keys' => []]),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWKS response does not contain a valid "keys" array.');

        $this->provider->getKeys('https://idp.example.com/.well-known/jwks.json');
    }
}
