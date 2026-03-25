<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'http_client', priority: 190)]
final class HttpClientDataCollector extends AbstractDataCollector
{
    private const MASKED_VALUE = '********';

    /** @var list<string> */
    private const SENSITIVE_HEADERS = [
        'authorization', 'cookie', 'x-api-key', 'x-auth-token',
        'proxy-authorization',
    ];

    /** @var array<string, float> */
    private array $pendingRequests = [];

    /** @var list<array{url: string, method: string, status_code: int, duration: float, start: float, request_headers: array<string, string>, response_headers: array<string, string>, response_size: int, error: string}> */
    private array $requests = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'http_client';
    }

    public function getLabel(): string
    {
        return 'HTTP Client';
    }

    /**
     * Record the start time for an outgoing HTTP request.
     *
     * @param mixed                $response   Pre-filter response (false to continue)
     * @param array<string, mixed> $parsedArgs Parsed request arguments
     * @param string               $url        Request URL
     * @return mixed
     */
    public function captureRequestStart(mixed $response, array $parsedArgs, string $url): mixed
    {
        $this->pendingRequests[$url] = microtime(true);

        return $response;
    }

    /**
     * Capture the completed HTTP response.
     *
     * @param mixed                $response   HTTP response array or WP_Error
     * @param string               $context    Context (response, etc.)
     * @param string               $class      Transport class name
     * @param mixed                $parsedArgs Parsed request arguments
     * @param string               $url        Request URL
     */
    public function captureRequestEnd(mixed $response, string $context, string $class, mixed $parsedArgs, string $url): void
    {
        $duration = 0.0;
        if (isset($this->pendingRequests[$url])) {
            $duration = (microtime(true) - $this->pendingRequests[$url]) * 1000;
        }

        $args = is_array($parsedArgs) ? $parsedArgs : [];
        $method = (string) ($args['method'] ?? 'GET');

        $statusCode = 0;
        $responseSize = 0;
        $error = '';

        if (is_array($response) && isset($response['response']['code'])) {
            $statusCode = (int) $response['response']['code'];
        }

        if (is_array($response) && isset($response['body'])) {
            $responseSize = strlen((string) $response['body']);
        }

        if (is_object($response) && method_exists($response, 'get_error_message')) {
            $error = (string) $response->get_error_message();
        }

        $requestHeaders = isset($args['headers']) && is_array($args['headers'])
            ? $this->maskSensitiveHeaders($args['headers'])
            : [];

        $responseHeaders = $this->extractResponseHeaders($response);

        $startTime = $this->pendingRequests[$url] ?? microtime(true) - $duration;

        $this->requests[] = [
            'url' => $url,
            'method' => $method,
            'status_code' => $statusCode,
            'duration' => $duration,
            'start' => $startTime,
            'request_headers' => $requestHeaders,
            'response_headers' => $responseHeaders,
            'response_size' => $responseSize,
            'error' => $error,
        ];

        unset($this->pendingRequests[$url]);
    }

    public function collect(): void
    {
        $totalTime = 0.0;
        $errorCount = 0;
        $slowCount = 0;

        foreach ($this->requests as $req) {
            $totalTime += $req['duration'];
            if ($req['error'] !== '' || $req['status_code'] >= 400) {
                $errorCount++;
            }
            if ($req['duration'] > 1000.0) {
                $slowCount++;
            }
        }

        $this->data = [
            'requests' => $this->requests,
            'total_count' => count($this->requests),
            'total_time' => $totalTime,
            'error_count' => $errorCount,
            'slow_count' => $slowCount,
        ];
    }

    public function getIndicatorValue(): string
    {
        $total = $this->data['total_count'] ?? 0;

        return $total > 0 ? (string) $total : '';
    }

    public function getIndicatorColor(): string
    {
        $errorCount = $this->data['error_count'] ?? 0;

        return $errorCount > 0 ? 'red' : 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->pendingRequests = [];
        $this->requests = [];
    }

    private function registerHooks(): void
    {
        add_filter('pre_http_request', [$this, 'captureRequestStart'], \PHP_INT_MAX, 3);
        add_action('http_api_debug', [$this, 'captureRequestEnd'], 10, 5);
    }

    /**
     * Extract response headers from the HTTP response.
     *
     * @return array<string, string>
     */
    private function extractResponseHeaders(mixed $response): array
    {
        if (!is_array($response) || !isset($response['headers'])) {
            return [];
        }

        $headers = $response['headers'];

        // Requests_Utility_CaseInsensitiveDictionary or similar iterable
        if (is_object($headers) && $headers instanceof \Traversable) {
            $result = [];
            foreach ($headers as $name => $value) {
                $result[(string) $name] = (string) $value;
            }

            return $this->maskSensitiveHeaders($result);
        }

        if (is_array($headers)) {
            $result = [];
            foreach ($headers as $name => $value) {
                $result[(string) $name] = (string) $value;
            }

            return $this->maskSensitiveHeaders($result);
        }

        return [];
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function maskSensitiveHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), self::SENSITIVE_HEADERS, true)) {
                $masked[$name] = self::MASKED_VALUE;
            } else {
                $masked[$name] = $value;
            }
        }

        return $masked;
    }
}
