<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\HttpClient\SafeHttpClient;

final class SafeHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        add_filter('pre_http_request', [$this, 'mockHttpResponse'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'mockHttpResponse'], 10);

        parent::tearDown();
    }

    /**
     * @param mixed               $preempt
     * @param array<string, mixed> $parsedArgs
     * @return array<string, mixed>
     */
    public function mockHttpResponse(mixed $preempt, array $parsedArgs, string $url): array
    {
        $responseData = [
            'reject_unsafe_urls' => $parsedArgs['reject_unsafe_urls'] ?? false,
            'timeout' => $parsedArgs['timeout'] ?? null,
        ];

        return [
            'headers' => ['content-type' => 'application/json'],
            'body' => (string) json_encode($responseData),
            'response' => ['code' => 200, 'message' => 'OK'],
            'cookies' => [],
            'filename' => null,
        ];
    }

    #[Test]
    public function rejectUnsafeUrlsIsEnabledByDefault(): void
    {
        $client = new SafeHttpClient();
        $response = $client->get('https://example.com/api');

        $json = $response->json();
        self::assertTrue($json['reject_unsafe_urls']);
    }

    #[Test]
    public function withOptionsCannotDisableRejectUnsafeUrls(): void
    {
        $client = (new SafeHttpClient())->withOptions(['reject_unsafe_urls' => false]);
        $response = $client->get('https://example.com/api');

        $json = $response->json();
        self::assertTrue($json['reject_unsafe_urls']);
    }

    #[Test]
    public function isInstanceOfHttpClient(): void
    {
        $client = new SafeHttpClient();

        self::assertInstanceOf(HttpClient::class, $client);
        self::assertInstanceOf(ClientInterface::class, $client);
    }

    #[Test]
    public function fluentMethodChainingPreservesType(): void
    {
        $client = new SafeHttpClient();

        $configured = $client->timeout(30)->asJson();

        self::assertInstanceOf(SafeHttpClient::class, $configured);
    }

    #[Test]
    public function withOptionsPreservesOtherOptions(): void
    {
        $client = (new SafeHttpClient())->withOptions(['timeout' => 60]);
        $response = $client->get('https://example.com/api');

        $json = $response->json();
        self::assertTrue($json['reject_unsafe_urls']);
        self::assertSame(60, $json['timeout']);
    }
}
