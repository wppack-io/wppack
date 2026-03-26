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

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Storage\Exception\InvalidArgumentException;

final class Dsn
{
    /**
     * @param array<string, string> $options
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
            throw new InvalidArgumentException(sprintf('The "%s" storage DSN is invalid.', $dsn));
        }

        $scheme = substr($dsn, 0, $colonPos);
        $rest = substr($dsn, $colonPos + 1);

        if (str_starts_with($rest, '//')) {
            $authority = substr($rest, 2);

            $questionPos = strpos($authority, '?');
            if ($questionPos !== false) {
                $query = substr($authority, $questionPos + 1);
                $authority = substr($authority, 0, $questionPos);
            }

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

            if ($authority !== '') {
                $slashPos = strpos($authority, '/');
                if ($slashPos !== false) {
                    $path = substr($authority, $slashPos);
                    $authority = substr($authority, 0, $slashPos);
                }

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
            }
        } else {
            throw new InvalidArgumentException(sprintf('The "%s" storage DSN is invalid.', $dsn));
        }

        $options = [];
        if ($query !== null) {
            parse_str($query, $parsed);
            /** @var array<string, string> $parsed */
            $options = array_map(strval(...), $parsed);
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
        return $this->options[$key] ?? $default;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        return $this->options;
    }
}
