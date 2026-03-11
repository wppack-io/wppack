<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'request', priority: 255)]
final class RequestDataCollector extends AbstractDataCollector
{
    private const MASKED_VALUE = '********';

    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd', 'secret', 'token',
        'api_key', 'apikey', 'api-key',
        'authorization', 'auth',
        'credit_card', 'card_number', 'cvv', 'ssn',
        'private_key', 'access_token', 'refresh_token',
    ];

    /** @var list<string> */
    private const SENSITIVE_HEADERS = [
        'authorization', 'cookie', 'x-api-key', 'x-auth-token',
        'proxy-authorization',
    ];

    private int $statusCode = 200;

    /** @var array<string, string> */
    private array $responseHeaders = [];

    /** @var list<array{url: string, args: array<string, mixed>, response: mixed}> */
    private array $httpApiCalls = [];

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'request';
    }

    public function getLabel(): string
    {
        return 'Request';
    }

    public function collect(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $url = $this->buildCurrentUrl();

        $this->data = [
            'method' => $method,
            'url' => $url,
            'status_code' => $this->statusCode,
            'content_type' => $this->resolveContentType(),
            'request_headers' => $this->maskSensitiveHeaders($this->collectRequestHeaders()),
            'response_headers' => $this->responseHeaders,
            'get_params' => $_GET,
            'post_params' => $this->maskSensitiveData($_POST),
            'cookies' => $this->maskSensitiveData($_COOKIE),
            'server_vars' => $this->collectServerVars(),
            'http_api_calls' => $this->maskHttpApiCalls($this->httpApiCalls),
        ];
    }

    public function getBadgeValue(): string
    {
        $method = $this->data['method'] ?? 'GET';
        $statusCode = $this->data['status_code'] ?? 200;

        return sprintf('%s %d', $method, $statusCode);
    }

    public function getBadgeColor(): string
    {
        $statusCode = $this->data['status_code'] ?? 200;

        return match (true) {
            $statusCode >= 400 => 'red',
            $statusCode >= 300 => 'yellow',
            default => 'green',
        };
    }

    public function reset(): void
    {
        parent::reset();
        $this->statusCode = 200;
        $this->responseHeaders = [];
        $this->httpApiCalls = [];
    }

    /**
     * Capture status code from the status_header filter.
     *
     * @param string $statusHeader Full status header string
     * @param int    $code         HTTP status code
     * @return string
     */
    public function captureStatusCode(string $statusHeader, int $code): string
    {
        $this->statusCode = $code;

        return $statusHeader;
    }

    /**
     * Capture response headers from the wp_headers filter.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function captureResponseHeaders(array $headers): array
    {
        $this->responseHeaders = $headers;

        return $headers;
    }

    /**
     * Track external HTTP API calls via the http_api_debug action.
     */
    public function captureHttpApiCall(mixed $response, string $context, string $class, mixed $parsedArgs, string $url): void
    {
        $this->httpApiCalls[] = [
            'url' => $url,
            'args' => is_array($parsedArgs) ? $parsedArgs : [],
            'response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function maskSensitiveData(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $masked[$key] = self::MASKED_VALUE;
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function maskSensitiveHeaders(array $headers): array
    {
        $masked = [];
        foreach ($headers as $name => $value) {
            $lower = strtolower($name);
            if (in_array($lower, self::SENSITIVE_HEADERS, true)) {
                $masked[$name] = self::MASKED_VALUE;
            } else {
                $masked[$name] = $value;
            }
        }

        return $masked;
    }

    /**
     * @param list<array{url: string, args: array<string, mixed>, response: mixed}> $calls
     * @return list<array{url: string, args: array<string, mixed>, response: mixed}>
     */
    private function maskHttpApiCalls(array $calls): array
    {
        $masked = [];
        foreach ($calls as $call) {
            $call['args'] = $this->maskSensitiveData($call['args']);
            $masked[] = $call;
        }

        return $masked;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($lower === $sensitive || str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function registerHooks(): void
    {
        if (function_exists('add_filter')) {
            add_filter('status_header', [$this, 'captureStatusCode'], 10, 2);
            add_filter('wp_headers', [$this, 'captureResponseHeaders'], 10, 1);
        }

        if (function_exists('add_action')) {
            add_action('http_api_debug', [$this, 'captureHttpApiCall'], 10, 5);
        }
    }

    /**
     * @return array<string, string>
     */
    private function collectRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            return $headers;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[ucwords(strtolower($name), '-')] = (string) $value;
            }
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectServerVars(): array
    {
        $relevantKeys = [
            'SERVER_NAME',
            'SERVER_ADDR',
            'SERVER_PORT',
            'SERVER_SOFTWARE',
            'SERVER_PROTOCOL',
            'DOCUMENT_ROOT',
            'REMOTE_ADDR',
            'REMOTE_PORT',
            'REQUEST_URI',
            'REQUEST_METHOD',
            'REQUEST_TIME',
            'REQUEST_TIME_FLOAT',
            'QUERY_STRING',
            'HTTPS',
            'CONTENT_TYPE',
            'CONTENT_LENGTH',
            'SCRIPT_FILENAME',
            'GATEWAY_INTERFACE',
            'PATH_INFO',
            'SCRIPT_NAME',
        ];

        $vars = [];
        foreach ($relevantKeys as $key) {
            if (isset($_SERVER[$key])) {
                $vars[$key] = $_SERVER[$key];
            }
        }

        return $vars;
    }

    private function resolveContentType(): string
    {
        foreach ($this->responseHeaders as $name => $value) {
            if (strtolower($name) === 'content-type') {
                return $value;
            }
        }

        foreach (headers_list() as $header) {
            if (str_starts_with(strtolower($header), 'content-type:')) {
                return trim(substr($header, 13));
            }
        }

        return '';
    }

    private function buildCurrentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return sprintf('%s://%s%s', $scheme, $host, $uri);
    }
}
