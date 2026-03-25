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

namespace WpPack\Component\Cache\Adapter;

use WpPack\Component\Cache\Exception\InvalidArgumentException;

final class Dsn
{
    /**
     * @param array<string, string|list<string>> $options
     */
    private function __construct(
        private readonly string $scheme,
        private readonly ?string $host,
        private readonly ?string $user,
        private readonly ?string $password,
        private readonly ?int $port,
        private readonly ?string $path,
        private readonly array $options,
    ) {}

    public static function fromString(string $dsn): self
    {
        // Handle DSNs like "redis:?host[...]&..." where parse_url fails on "redis:?"
        // Also handle "redis:///path" for Unix sockets
        $scheme = null;
        $host = null;
        $user = null;
        $password = null;
        $port = null;
        $path = null;
        $query = null;

        // Extract scheme
        $colonPos = strpos($dsn, ':');
        if ($colonPos === false) {
            throw new InvalidArgumentException(sprintf('The "%s" cache DSN is invalid.', $dsn));
        }

        $scheme = substr($dsn, 0, $colonPos);
        $rest = substr($dsn, $colonPos + 1);

        if (str_starts_with($rest, '//')) {
            // Standard URI: scheme://[user:pass@]host[:port][/path][?query]
            // parse_url fails on some valid Redis DSNs, so we parse manually for those cases
            $authority = substr($rest, 2);

            // Split query string
            $questionPos = strpos($authority, '?');
            if ($questionPos !== false) {
                $query = substr($authority, $questionPos + 1);
                $authority = substr($authority, 0, $questionPos);
            }

            // Split userinfo from host
            $atPos = strpos($authority, '@');
            if ($atPos !== false) {
                $userinfo = substr($authority, 0, $atPos);
                $authority = substr($authority, $atPos + 1);

                $colonPos = strpos($userinfo, ':');
                if ($colonPos !== false) {
                    $user = urldecode(substr($userinfo, 0, $colonPos));
                    $password = urldecode(substr($userinfo, $colonPos + 1));
                } else {
                    $user = urldecode($userinfo);
                }
            }

            // Parse host[:port][/path]
            if ($authority !== '') {
                // Check for /path
                $slashPos = strpos($authority, '/');
                if ($slashPos !== false) {
                    $path = substr($authority, $slashPos);
                    $authority = substr($authority, 0, $slashPos);
                }

                // Check for :port
                $colonPos = strrpos($authority, ':');
                if ($colonPos !== false) {
                    $portStr = substr($authority, $colonPos + 1);
                    if (is_numeric($portStr)) {
                        $port = (int) $portStr;
                        $authority = substr($authority, 0, $colonPos);
                    }
                }

                if ($authority !== '') {
                    $host = $authority;
                }
            } elseif (str_starts_with($rest, '///')) {
                // redis:///var/run/redis.sock — empty authority, path follows
                $pathPart = substr($rest, 2);
                $questionPos2 = strpos($pathPart, '?');
                if ($questionPos2 !== false) {
                    $path = substr($pathPart, 0, $questionPos2);
                } else {
                    $path = $pathPart;
                }
            }
        } elseif (str_starts_with($rest, '?')) {
            // No-host URI: scheme:?query (for multi-host DSNs like cluster/sentinel)
            $query = substr($rest, 1);
        } else {
            throw new InvalidArgumentException(sprintf('The "%s" cache DSN is invalid.', $dsn));
        }

        $options = [];
        if ($query !== null) {
            $options = self::parseQuery($query);
        }

        return new self(
            scheme: $scheme,
            host: $host,
            user: $user,
            password: $password,
            port: $port,
            path: $path,
            options: $options,
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function getUser(): ?string
    {
        return $this->user;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function getOption(string $key, ?string $default = null): ?string
    {
        $value = $this->options[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (\is_array($value)) {
            return $default;
        }

        return $value;
    }

    /** @return list<string> */
    public function getArrayOption(string $key): array
    {
        $value = $this->options[$key] ?? null;

        if ($value === null) {
            return [];
        }

        if (\is_string($value)) {
            return [$value];
        }

        return $value;
    }

    /** @return array<string, string|list<string>> */
    private static function parseQuery(string $query): array
    {
        $options = [];
        $pairs = explode('&', $query);

        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }

            $eqPos = strpos($pair, '=');
            if ($eqPos === false) {
                $key = urldecode($pair);
                $value = '';
            } else {
                $key = urldecode(substr($pair, 0, $eqPos));
                $value = urldecode(substr($pair, $eqPos + 1));
            }

            // Handle array parameters: host[node1:6379] or host[]
            if (str_ends_with($key, ']')) {
                $bracketPos = strpos($key, '[');
                if ($bracketPos !== false) {
                    $arrayKey = substr($key, 0, $bracketPos);
                    $arrayValue = substr($key, $bracketPos + 1, -1);

                    // host[node1:6379] -> key=host, value=node1:6379
                    // host[]=value -> key=host, value=value
                    $itemValue = $arrayValue !== '' ? $arrayValue : $value;

                    if (!isset($options[$arrayKey])) {
                        $options[$arrayKey] = [];
                    }
                    if (\is_string($options[$arrayKey])) {
                        $options[$arrayKey] = [$options[$arrayKey]];
                    }
                    $options[$arrayKey][] = $itemValue;
                    continue;
                }
            }

            $options[$key] = $value;
        }

        return $options;
    }
}
