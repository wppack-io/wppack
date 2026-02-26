<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use WpPack\Component\HttpClient\Exception\ConnectionException;

final class HttpClient implements ClientInterface
{
    /** @var array<string, string> */
    private array $headers = [];

    private ?int $timeout = null;

    private ?string $baseUri = null;

    private ?string $bodyFormat = null;

    /** @var array<string, string> */
    private array $queryParams = [];

    /** @var list<array{name: string, contents: mixed, filename: ?string}> */
    private array $attachments = []; // @phpstan-ignore property.onlyWritten

    /** @var array<string, mixed> */
    private array $options = [];

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

        /** @var array{body: string, headers: array<string, string>, response: array{code: int, message: string}}|\WP_Error $result */
        $result = wp_remote_request($uri, $args);

        if (is_wp_error($result)) {
            throw new ConnectionException(
                $result->get_error_message(),
                $request,
            );
        }

        $statusCode = (int) $result['response']['code'];
        $reasonPhrase = $result['response']['message'] ?? '';
        $responseHeaders = [];

        if (isset($result['headers'])) {
            $headers = $result['headers'];
            if (\is_object($headers) && $headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary) {
                $headers = $headers->getAll();
            }
            if (\is_array($headers)) {
                /** @var array<string, string|list<string>> $headers */
                foreach ($headers as $name => $value) {
                    $responseHeaders[$name] = \is_array($value) ? $value : [$value];
                }
            }
        }

        $body = $result['body'] ?? '';

        return new WpPackResponse(
            statusCode: $statusCode,
            headers: $responseHeaders,
            body: $body,
            reasonPhrase: $reasonPhrase,
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withBasicAuth(string $user, string $password): self
    {
        return $this->withHeaders([
            'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
        ]);
    }

    public function timeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = $seconds;

        return $clone;
    }

    public function baseUri(string $baseUri): self
    {
        $clone = clone $this;
        $clone->baseUri = rtrim($baseUri, '/');

        return $clone;
    }

    public function asJson(): self
    {
        $clone = clone $this;
        $clone->bodyFormat = 'json';
        $clone->headers['Content-Type'] = 'application/json';
        $clone->headers['Accept'] = 'application/json';

        return $clone;
    }

    public function asForm(): self
    {
        $clone = clone $this;
        $clone->bodyFormat = 'form';
        $clone->headers['Content-Type'] = 'application/x-www-form-urlencoded';

        return $clone;
    }

    public function asMultipart(): self
    {
        $clone = clone $this;
        $clone->bodyFormat = 'multipart';

        return $clone;
    }

    /**
     * @param array<string, string> $params
     */
    public function query(array $params): self
    {
        $clone = clone $this;
        $clone->queryParams = array_merge($clone->queryParams, $params);

        return $clone;
    }

    public function attach(string $name, mixed $contents, ?string $filename = null): self
    {
        $clone = clone $this;
        $clone->attachments[] = ['name' => $name, 'contents' => $contents, 'filename' => $filename];

        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): self
    {
        $clone = clone $this;
        $clone->options = array_merge($clone->options, $options);

        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): WpPackResponse
    {
        return $this->request('GET', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function post(string $url, array $options = []): WpPackResponse
    {
        return $this->request('POST', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(string $url, array $options = []): WpPackResponse
    {
        return $this->request('PUT', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function patch(string $url, array $options = []): WpPackResponse
    {
        return $this->request('PATCH', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function delete(string $url, array $options = []): WpPackResponse
    {
        return $this->request('DELETE', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function head(string $url, array $options = []): WpPackResponse
    {
        return $this->request('HEAD', $url, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function request(string $method, string $url, array $options = []): WpPackResponse
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

        $request = new WpPackRequest($method, $url, $headers, $body);

        /** @var WpPackResponse */
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
