<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient;

use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private string $scheme;

    private string $userInfo;

    private string $host;

    private ?int $port;

    private string $path;

    private string $query;

    private string $fragment;

    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            $this->scheme = '';
            $this->userInfo = '';
            $this->host = '';
            $this->port = null;
            $this->path = '';
            $this->query = '';
            $this->fragment = '';

            return;
        }

        $parts = parse_url($uri);
        if ($parts === false) {
            throw new \InvalidArgumentException(sprintf('Unable to parse URI: "%s".', $uri));
        }

        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';

        $userInfo = $parts['user'] ?? '';
        if (isset($parts['pass'])) {
            $userInfo .= ':' . $parts['pass'];
        }
        $this->userInfo = $userInfo;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        $authority = $this->host;
        if ($authority === '') {
            return '';
        }

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $clone = clone $this;
        $clone->scheme = strtolower($scheme);

        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $userInfo = $user;
        if ($password !== null && $password !== '') {
            $userInfo .= ':' . $password;
        }

        $clone = clone $this;
        $clone->userInfo = $userInfo;

        return $clone;
    }

    public function withHost(string $host): UriInterface
    {
        $clone = clone $this;
        $clone->host = strtolower($host);

        return $clone;
    }

    public function withPort(?int $port): UriInterface
    {
        $clone = clone $this;
        $clone->port = $port !== null ? $this->filterPort($port) : null;

        return $clone;
    }

    public function withPath(string $path): UriInterface
    {
        $clone = clone $this;
        $clone->path = $path;

        return $clone;
    }

    public function withQuery(string $query): UriInterface
    {
        $clone = clone $this;
        $clone->query = $query;

        return $clone;
    }

    public function withFragment(string $fragment): UriInterface
    {
        $clone = clone $this;
        $clone->fragment = $fragment;

        return $clone;
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();
        if ($authority !== '') {
            $uri .= '//' . $authority;
        }

        $path = $this->path;
        if ($path !== '') {
            if ($authority !== '' && !str_starts_with($path, '/')) {
                $path = '/' . $path;
            } elseif ($authority === '' && str_starts_with($path, '//')) {
                $path = '/' . ltrim($path, '/');
            }
        }
        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    private function filterPort(int $port): ?int
    {
        if ($port < 0 || $port > 65535) {
            throw new \InvalidArgumentException(sprintf('Invalid port: %d. Must be between 0 and 65535.', $port));
        }

        $defaultPorts = ['http' => 80, 'https' => 443];
        if (isset($defaultPorts[$this->scheme]) && $defaultPorts[$this->scheme] === $port) {
            return null;
        }

        return $port;
    }
}
