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

namespace WPPack\Component\Dsn;

use WPPack\Component\Dsn\Exception\InvalidDsnException;

/**
 * Parses and represents a Data Source Name (DSN) string.
 *
 * Supports standard URI format: scheme://[user:pass@]host[:port][/path][?query]
 * Also supports no-host URIs (scheme:?query), Unix sockets (scheme:///path),
 * and array query parameters (key[]=value).
 */
final class Dsn
{
    /**
     * @param array<string, string|list<string>> $options
     */
    private function __construct(
        private readonly string $scheme,
        private readonly ?string $host,
        private readonly ?string $user,
        #[\SensitiveParameter]
        private readonly ?string $password,
        private readonly ?int $port,
        private readonly ?string $path,
        private readonly array $options,
    ) {}

    public static function fromString(string $dsn): self
    {
        $scheme = null;
        $host = null;
        $user = null;
        $password = null;
        $port = null;
        $path = null;
        $query = null;

        $colonPos = strpos($dsn, ':');

        if ($colonPos === false) {
            throw new InvalidDsnException(sprintf("The DSN \"%s\" must contain a scheme.", $dsn));
        }

        $scheme = substr($dsn, 0, $colonPos);
        if ($scheme === '') {
            throw new InvalidDsnException(sprintf("The DSN \"%s\" must contain a scheme.", $dsn));
        }

        $rest = substr($dsn, $colonPos + 1);

        if (str_starts_with($rest, '//')) {
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

                $userColonPos = strpos($userinfo, ':');

                if ($userColonPos !== false) {
                    $user = urldecode(substr($userinfo, 0, $userColonPos));
                    $password = urldecode(substr($userinfo, $userColonPos + 1));
                } else {
                    $user = urldecode($userinfo);
                }
            }

            // Parse host[:port][/path]
            if ($authority !== '') {
                $slashPos = strpos($authority, '/');

                if ($slashPos !== false) {
                    $path = substr($authority, $slashPos);
                    $authority = substr($authority, 0, $slashPos);
                }

                $hostColonPos = strrpos($authority, ':');

                if ($hostColonPos !== false) {
                    $portStr = substr($authority, $hostColonPos + 1);

                    if (is_numeric($portStr)) {
                        $port = (int) $portStr;
                        $authority = substr($authority, 0, $hostColonPos);
                    }
                }

                if ($authority !== '') {
                    $host = $authority;
                }
            } elseif (str_starts_with($rest, '///')) {
                // scheme:///path — empty authority, path follows (e.g., Unix sockets, SQLite)
                $pathPart = substr($rest, 2);
                $questionPos2 = strpos($pathPart, '?');

                if ($questionPos2 !== false) {
                    $query = substr($pathPart, $questionPos2 + 1);
                    $path = substr($pathPart, 0, $questionPos2);
                } else {
                    $path = $pathPart;
                }
            }
        } elseif (str_starts_with($rest, '?')) {
            // No-host URI: scheme:?query (e.g., cluster/sentinel configs)
            $query = substr($rest, 1);
        } else {
            throw new InvalidDsnException(sprintf("The DSN \"%s\" is invalid.", $dsn));
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

    /**
     * @phpstan-return ($default is null ? ?string : string)
     */
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
    public function getOptions(): array
    {
        return $this->options;
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

            // Handle array parameters: key[node1:6379] or key[]=value
            if (str_ends_with($key, ']')) {
                $bracketPos = strpos($key, '[');

                if ($bracketPos !== false) {
                    $arrayKey = substr($key, 0, $bracketPos);
                    $arrayValue = substr($key, $bracketPos + 1, -1);

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
