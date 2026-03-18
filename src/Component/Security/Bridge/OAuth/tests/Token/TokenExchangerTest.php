<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\Tests\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;
use WpPack\Component\Security\Bridge\OAuth\Token\TokenExchanger;

#[CoversClass(TokenExchanger::class)]
final class TokenExchangerTest extends TestCase
{
    private HttpClient $httpClient;
    private TokenExchanger $exchanger;

    /** @var array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null */
    private ?array $mockResponse = null;

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
        $this->exchanger = new TokenExchanger($this->httpClient);

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);
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
    public function exchangeReturnsTokenSet(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'access-123',
                'token_type' => 'Bearer',
                'id_token' => 'id-token-456',
                'refresh_token' => 'refresh-789',
                'expires_in' => 3600,
                'scope' => 'openid profile email',
            ]),
        ];

        $tokenSet = $this->exchanger->exchange(
            'https://idp.example.com/token',
            'auth-code-abc',
            'https://example.com/callback',
            'client-id',
            'client-secret',
        );

        self::assertSame('access-123', $tokenSet->getAccessToken());
        self::assertSame('Bearer', $tokenSet->getTokenType());
        self::assertSame('id-token-456', $tokenSet->getIdToken());
        self::assertSame('refresh-789', $tokenSet->getRefreshToken());
        self::assertSame(3600, $tokenSet->getExpiresIn());
    }

    #[Test]
    public function exchangeWithCodeVerifier(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'access-pkce',
                'token_type' => 'Bearer',
            ]),
        ];

        $tokenSet = $this->exchanger->exchange(
            'https://idp.example.com/token',
            'auth-code-abc',
            'https://example.com/callback',
            'client-id',
            'client-secret',
            'code-verifier-xyz',
        );

        self::assertSame('access-pkce', $tokenSet->getAccessToken());
    }

    #[Test]
    public function exchangeThrowsOnHttpEndpoint(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token endpoint must use HTTPS.');

        $this->exchanger->exchange(
            'http://idp.example.com/token',
            'auth-code-abc',
            'https://example.com/callback',
            'client-id',
            'client-secret',
        );
    }

    #[Test]
    public function exchangeThrowsOnHttpError(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 500, 'message' => 'Internal Server Error'],
            'headers' => [],
            'body' => 'Server Error',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token exchange failed.');

        $this->exchanger->exchange(
            'https://idp.example.com/token',
            'auth-code-abc',
            'https://example.com/callback',
            'client-id',
            'client-secret',
        );
    }

    #[Test]
    public function exchangeThrowsOnErrorInResponse(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'The authorization code has expired.',
            ]),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token exchange failed.');

        $this->exchanger->exchange(
            'https://idp.example.com/token',
            'expired-code',
            'https://example.com/callback',
            'client-id',
            'client-secret',
        );
    }
}
