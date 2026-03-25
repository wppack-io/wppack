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

namespace WpPack\Component\HttpClient;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\RequestInterface;
use WpPack\Component\HttpClient\Exception\RequestException;

final class Response implements ResponseInterface
{
    private int $statusCode;

    private string $reasonPhrase;

    /** @var array<string, list<string>> */
    private array $headerValues;

    /** @var array<string, string> */
    private array $headerNames;

    private StreamInterface $body;

    private string $protocolVersion;

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        StreamInterface|string $body = '',
        string $protocolVersion = '1.1',
        string $reasonPhrase = '',
    ) {
        $this->statusCode = $statusCode;
        $this->reasonPhrase = $reasonPhrase;
        $this->protocolVersion = $protocolVersion;

        $this->headerValues = [];
        $this->headerNames = [];
        foreach ($headers as $name => $value) {
            $normalized = strtolower($name);
            $this->headerNames[$normalized] = $name;
            $this->headerValues[$name] = \is_array($value) ? $value : [$value];
        }

        $this->body = $body instanceof StreamInterface ? $body : new Stream($body);
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headerValues;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $normalized = strtolower($name);
        if (!isset($this->headerNames[$normalized])) {
            return [];
        }

        return $this->headerValues[$this->headerNames[$normalized]];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $normalized = strtolower($name);

        if (isset($clone->headerNames[$normalized])) {
            unset($clone->headerValues[$clone->headerNames[$normalized]]);
        }

        $clone->headerNames[$normalized] = $name;
        $clone->headerValues[$name] = \is_array($value) ? $value : [$value];

        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $normalized = strtolower($name);
        $values = \is_array($value) ? $value : [$value];

        if (isset($clone->headerNames[$normalized])) {
            $existing = $clone->headerNames[$normalized];
            $clone->headerValues[$existing] = array_merge($clone->headerValues[$existing], $values);
        } else {
            $clone->headerNames[$normalized] = $name;
            $clone->headerValues[$name] = $values;
        }

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $normalized = strtolower($name);

        if (isset($clone->headerNames[$normalized])) {
            unset($clone->headerValues[$clone->headerNames[$normalized]]);
            unset($clone->headerNames[$normalized]);
        }

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): static
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    // --- Fluent helpers ---

    public function status(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headerValues;
    }

    public function header(string $name): ?string
    {
        $values = $this->getHeader($name);

        return $values !== [] ? $values[0] : null;
    }

    public function body(): string
    {
        return (string) $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body(), true);
        if (!\is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function failed(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 600;
    }

    public function clientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function serverError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * @throws RequestException if the response indicates a client or server error.
     */
    public function throw(?RequestInterface $request = null): self
    {
        if ($this->failed()) {
            throw new RequestException($this, $request);
        }

        return $this;
    }
}
