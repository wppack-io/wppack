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

namespace WPPack\Component\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use WPPack\Component\HttpClient\Exception\ConnectionException;

class HttpClient implements ClientInterface
{
    /** @var array<string, string> */
    private array $headers = [];

    private ?int $timeout = null;

    private ?string $baseUri = null;

    private ?string $bodyFormat = null;

    /** @var array<string, string> */
    private array $queryParams = [];

    /** @var array<string, mixed> */
    protected array $options = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $uri = (string) $request->getUri();

        $args = [
            'method' => $request->getMethod(),
            'headers' => [],
            'body' => (string) $request->getBody(),
        ];

        foreach ($request->getHeaders() as $name => $values) {
            $args['headers'][$name] = implode(', ', $values);
        }

        if ($this->timeout !== null) {
            $args['timeout'] = $this->timeout;
        }

        foreach ($this->options as $key => $value) {
            $args[$key] = $value;
        }

        $result = wp_remote_request($uri, $args);

        // Narrow via instanceof rather than is_wp_error(): PHPStan handles
        // instanceof natively, while is_wp_error()'s narrowing depends on a
        // stub extension that doesn't always align with the loose return
        // type wp_remote_request() advertises.
        if ($result instanceof \WP_Error) {
            throw new ConnectionException(
                $result->get_error_message(),
                $request,
            );
        }

        // wp_remote_response is array{response: array{code, message}, body, headers}
        // in practice, but the WP stub flattens to array<string, array|string>;
        // pull through wp_remote_retrieve_* helpers which encapsulate the
        // null-safe shape.
        $statusCode = (int) wp_remote_retrieve_response_code($result);
        $reasonPhrase = (string) wp_remote_retrieve_response_message($result);
        $responseHeaders = [];
        $headers = wp_remote_retrieve_headers($result);
        if ($headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary) {
            $headers = $headers->getAll();
        }
        /** @var array<string, string|list<string>> $headers */
        foreach ($headers as $name => $value) {
            $responseHeaders[$name] = \is_array($value) ? $value : [$value];
        }

        $body = (string) wp_remote_retrieve_body($result);

        return new Response(
            statusCode: $statusCode,
            headers: $responseHeaders,
            body: $body,
            reasonPhrase: $reasonPhrase,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withBasicAuth(string $user, #[\SensitiveParameter] string $password): static
    {
        return $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
        ]);
    }

    public function timeout(int $seconds): static
    {
        $clone = clone $this;
        $clone->timeout = $seconds;

        return $clone;
    }

    public function baseUri(string $baseUri): static
    {
        $clone = clone $this;
        $clone->baseUri = rtrim($baseUri, '/');

        return $clone;
    }

    public function asJson(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'json';
        $clone->headers['Content-Type'] = 'application/json';
        $clone->headers['Accept'] = 'application/json';

        return $clone;
    }

    public function asForm(): static
    {
        $clone = clone $this;
        $clone->bodyFormat = 'form';
        $clone->headers['Content-Type'] = 'application/x-www-form-urlencoded';

        return $clone;
    }

    /**
     * @param array<string, string> $params
     */
    public function query(array $params): static
    {
        $clone = clone $this;
        $clone->queryParams = array_merge($clone->queryParams, $params);

        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->options = array_merge($clone->options, $options);

        return $clone;
    }

    public function safe(): static
    {
        return $this->withOptions(['reject_unsafe_urls' => true]);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): Response
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $url, array $options = []): Response
    {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(string $url, array $options = []): Response
    {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function patch(string $url, array $options = []): Response
    {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function delete(string $url, array $options = []): Response
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function head(string $url, array $options = []): Response
    {
        return $this->request('HEAD', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options = []): Response
    {
        $url = $this->buildUrl($url);

        $headers = $this->headers;
        $body = '';

        if (isset($options['headers'])) {
            /** @var array<string, string> $optionHeaders */
            $optionHeaders = $options['headers'];
            $headers = array_merge($headers, $optionHeaders);
        }

        $data = $options['body'] ?? $options['json'] ?? $options['form_params'] ?? null;

        if ($data !== null) {
            if ($this->bodyFormat === 'json' || isset($options['json'])) {
                $body = json_encode($data, \JSON_THROW_ON_ERROR);
                $headers['Content-Type'] ??= 'application/json';
            } elseif ($this->bodyFormat === 'form' || isset($options['form_params'])) {
                /** @var array<string, string> $data */
                $body = http_build_query($data);
                $headers['Content-Type'] ??= 'application/x-www-form-urlencoded';
            } elseif (\is_string($data)) {
                $body = $data;
            }
        }

        $request = new Request($method, $url, $headers, $body);

        /** @var Response */
        return $this->sendRequest($request);
    }

    private function buildUrl(string $url): string
    {
        if ($this->baseUri !== null && !str_contains($url, '://')) {
            $url = $this->baseUri . '/' . ltrim($url, '/');
        }

        if ($this->queryParams !== []) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($this->queryParams);
        }

        return $url;
    }
}
