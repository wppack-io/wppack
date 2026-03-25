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

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\HttpClientDataCollector;

final class HttpClientDataCollectorTest extends TestCase
{
    private HttpClientDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new HttpClientDataCollector();
    }

    #[Test]
    public function getNameReturnsHttpClient(): void
    {
        self::assertSame('http_client', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsHttp(): void
    {
        self::assertSame('HTTP Client', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithNoRequestsReturnsDefaults(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['requests']);
        self::assertSame(0, $data['total_count']);
        self::assertSame(0.0, $data['total_time']);
        self::assertSame(0, $data['error_count']);
        self::assertSame(0, $data['slow_count']);
    }

    #[Test]
    public function captureRequestStartStoresStartTime(): void
    {
        $result = $this->collector->captureRequestStart(false, ['method' => 'GET'], 'https://example.com');

        // Must return $response as-is to not short-circuit the real request
        self::assertFalse($result);

        // After start but before end, no completed requests should exist
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
    }

    #[Test]
    public function captureRequestEndRecordsRequest(): void
    {
        $url = 'https://api.example.com/data';

        $this->collector->captureRequestStart(false, ['method' => 'POST'], $url);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'response body',
            'headers' => [],
        ];

        $this->collector->captureRequestEnd(
            $response,
            'response',
            'WP_Http',
            ['method' => 'POST', 'headers' => ['Content-Type' => 'application/json']],
            $url,
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertCount(1, $data['requests']);

        $request = $data['requests'][0];
        self::assertSame($url, $request['url']);
        self::assertSame('POST', $request['method']);
        self::assertSame(200, $request['status_code']);
        self::assertGreaterThanOrEqual(0.0, $request['duration']);
        self::assertSame(strlen('response body'), $request['response_size']);
        self::assertSame('', $request['error']);
        self::assertSame('application/json', $request['request_headers']['Content-Type']);
    }

    #[Test]
    public function sensitiveHeadersAreMasked(): void
    {
        $url = 'https://api.example.com/secure';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => '',
            'headers' => [],
        ];

        $this->collector->captureRequestEnd(
            $response,
            'response',
            'WP_Http',
            [
                'method' => 'GET',
                'headers' => [
                    'Authorization' => 'Bearer secret-token',
                    'X-Api-Key' => 'my-api-key',
                    'Accept' => 'application/json',
                ],
            ],
            $url,
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        $headers = $data['requests'][0]['request_headers'];
        self::assertSame('********', $headers['Authorization']);
        self::assertSame('********', $headers['X-Api-Key']);
        self::assertSame('application/json', $headers['Accept']);
    }

    #[Test]
    public function responseHeadersAreExtracted(): void
    {
        $url = 'https://api.example.com/headers';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'ok',
            'headers' => ['Content-Type' => 'application/json', 'X-Request-Id' => 'abc123'],
        ];

        $this->collector->captureRequestEnd(
            $response,
            'response',
            'WP_Http',
            ['method' => 'GET', 'headers' => []],
            $url,
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('application/json', $data['requests'][0]['response_headers']['Content-Type']);
        self::assertSame('abc123', $data['requests'][0]['response_headers']['X-Request-Id']);
    }

    #[Test]
    public function statusCode400CountsAsError(): void
    {
        $url = 'https://api.example.com/bad-request';

        $this->collector->captureRequestStart(false, ['method' => 'POST'], $url);

        $response = [
            'response' => ['code' => 400, 'message' => 'Bad Request'],
            'body' => '{"error": "invalid input"}',
            'headers' => [],
        ];

        $this->collector->captureRequestEnd(
            $response,
            'response',
            'WP_Http',
            ['method' => 'POST', 'headers' => []],
            $url,
        );

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['error_count']);
        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNoRequests(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsRedOnError(): void
    {
        $url = 'https://failing.example.com';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        $error = new class {
            public function get_error_message(): string
            {
                return 'Connection timed out';
            }
        };

        $this->collector->captureRequestEnd(
            $error,
            'response',
            'WP_Http',
            ['method' => 'GET', 'headers' => []],
            $url,
        );

        $this->collector->collect();

        self::assertSame('red', $this->collector->getIndicatorColor());
        self::assertSame('1', $this->collector->getIndicatorValue());

        $data = $this->collector->getData();
        self::assertSame(1, $data['error_count']);
        self::assertSame('Connection timed out', $data['requests'][0]['error']);
        self::assertSame(0, $data['requests'][0]['status_code']);
    }

    #[Test]
    public function resetClearsData(): void
    {
        $url = 'https://example.com/reset-test';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);
        $this->collector->captureRequestEnd(
            ['response' => ['code' => 200, 'message' => 'OK'], 'body' => 'ok', 'headers' => []],
            'response',
            'WP_Http',
            ['method' => 'GET', 'headers' => []],
            $url,
        );

        $this->collector->collect();
        self::assertSame(1, $this->collector->getData()['total_count']);

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collect should return defaults
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
        self::assertSame([], $data['requests']);
    }

    #[Test]
    public function slowRequestIsCountedAsSlow(): void
    {
        $url = 'https://slow.example.com/api';

        // Capture start with a very old timestamp so duration > 1000ms
        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        // Manipulate the pending start time to simulate slow request
        $ref = new \ReflectionProperty($this->collector, 'pendingRequests');
        $pending = $ref->getValue($this->collector);
        $pending[$url] = microtime(true) - 2.0; // 2 seconds ago
        $ref->setValue($this->collector, $pending);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'ok',
            'headers' => [],
        ];

        $this->collector->captureRequestEnd($response, 'response', 'WP_Http', ['method' => 'GET'], $url);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['slow_count']);
        self::assertGreaterThan(1000.0, $data['requests'][0]['duration']);
    }

    #[Test]
    public function captureRequestEndWithoutHeadersInArgsUsesEmptyArray(): void
    {
        $url = 'https://example.com/no-headers';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => '',
            'headers' => [],
        ];

        // Pass args without 'headers' key
        $this->collector->captureRequestEnd($response, 'response', 'WP_Http', ['method' => 'GET'], $url);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['requests'][0]['request_headers']);
    }

    #[Test]
    public function extractResponseHeadersWithTraversable(): void
    {
        $url = 'https://example.com/traversable-headers';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        // Create a Traversable headers object (mimics Requests_Utility_CaseInsensitiveDictionary)
        $headers = new \ArrayIterator(['Content-Type' => 'text/html', 'X-Custom' => 'value']);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'ok',
            'headers' => $headers,
        ];

        $this->collector->captureRequestEnd($response, 'response', 'WP_Http', ['method' => 'GET', 'headers' => []], $url);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('text/html', $data['requests'][0]['response_headers']['Content-Type']);
        self::assertSame('value', $data['requests'][0]['response_headers']['X-Custom']);
    }

    #[Test]
    public function extractResponseHeadersWithNonArrayNonTraversableReturnsEmpty(): void
    {
        $url = 'https://example.com/no-parseable-headers';

        $this->collector->captureRequestStart(false, ['method' => 'GET'], $url);

        $response = [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body' => 'ok',
            'headers' => 'plain-string-not-iterable',
        ];

        $this->collector->captureRequestEnd($response, 'response', 'WP_Http', ['method' => 'GET', 'headers' => []], $url);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['requests'][0]['response_headers']);
    }
}
