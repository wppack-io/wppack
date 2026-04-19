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

namespace WPPack\Component\Security\Bridge\OAuth\Tests\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpClient\HttpClient;
use WPPack\Component\Security\Bridge\OAuth\Token\OAuthTokenSet;
use WPPack\Component\Security\Bridge\OAuth\Token\TokenRefresher;

#[CoversClass(TokenRefresher::class)]
final class TokenRefresherTest extends TestCase
{
    private HttpClient $httpClient;
    private TokenRefresher $refresher;

    /** @var array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|null */
    private ?array $mockResponse = null;

    protected function setUp(): void
    {
        $this->httpClient = new HttpClient();
        $this->refresher = new TokenRefresher($this->httpClient);

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
    public function refreshReturnsNewTokenSet(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'new-access-token',
                'token_type' => 'Bearer',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 7200,
            ]),
        ];

        $tokenSet = $this->refresher->refresh(
            'https://idp.example.com/token',
            'old-refresh-token',
            'client-id',
            'client-secret',
        );

        self::assertSame('new-access-token', $tokenSet->getAccessToken());
        self::assertSame('new-refresh-token', $tokenSet->getRefreshToken());
        self::assertSame(7200, $tokenSet->getExpiresIn());
    }

    #[Test]
    public function refreshWithScopes(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'access_token' => 'scoped-access-token',
                'token_type' => 'Bearer',
                'scope' => 'openid profile',
            ]),
        ];

        $tokenSet = $this->refresher->refresh(
            'https://idp.example.com/token',
            'refresh-token',
            'client-id',
            'client-secret',
            ['openid', 'profile'],
        );

        self::assertSame('scoped-access-token', $tokenSet->getAccessToken());
        self::assertSame('openid profile', $tokenSet->getScope());
    }

    #[Test]
    public function refreshThrowsOnHttpEndpoint(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token endpoint must use HTTPS.');

        $this->refresher->refresh(
            'http://idp.example.com/token',
            'refresh-token',
            'client-id',
            'client-secret',
        );
    }

    #[Test]
    public function refreshThrowsOnHttpError(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 400, 'message' => 'Bad Request'],
            'headers' => [],
            'body' => 'Bad Request',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token refresh failed.');

        $this->refresher->refresh(
            'https://idp.example.com/token',
            'invalid-refresh-token',
            'client-id',
            'client-secret',
        );
    }

    #[Test]
    public function refreshThrowsOnErrorInResponse(): void
    {
        $this->mockResponse = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'The refresh token has been revoked.',
            ]),
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Token refresh failed.');

        $this->refresher->refresh(
            'https://idp.example.com/token',
            'revoked-refresh-token',
            'client-id',
            'client-secret',
        );
    }
}
