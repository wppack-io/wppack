<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\Exception\InvalidArgumentException;

final class Dsn
{
    /**
     * @param array<string, string> $options
     */
    private function __construct(
        private readonly string $scheme,
        private readonly string $host,
        private readonly ?string $user,
        private readonly ?string $password,
        private readonly ?int $port,
        private readonly array $options,
    ) {}

    public static function fromString(string $dsn): self
    {
        if (false === $parsedDsn = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN is invalid.', $dsn));
        }

        if (!isset($parsedDsn['scheme'])) {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN must contain a scheme.', $dsn));
        }

        if (!isset($parsedDsn['host']) || $parsedDsn['host'] === '') {
            throw new InvalidArgumentException(sprintf('The "%s" mailer DSN must contain a host.', $dsn));
        }

        $user = isset($parsedDsn['user']) ? urldecode($parsedDsn['user']) : null;
        $password = isset($parsedDsn['pass']) ? urldecode($parsedDsn['pass']) : null;
        $port = $parsedDsn['port'] ?? null;

        $options = [];
        if (isset($parsedDsn['query'])) {
            parse_str($parsedDsn['query'], $parsed);
            foreach ($parsed as $key => $value) {
                if (\is_string($value)) {
                    $options[$key] = $value;
                }
            }
        }

        return new self(
            scheme: $parsedDsn['scheme'],
            host: $parsedDsn['host'],
            user: $user,
            password: $password,
            port: $port,
            options: $options,
        );
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
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

    public function getOption(string $key, ?string $default = null): ?string
    {
        return $this->options[$key] ?? $default;
    }

    public function __toString(): string
    {
        $userInfo = '';
        if ($this->user !== null) {
            $password = $this->password !== null ? ':****' : '';
            $userInfo = $this->user . $password . '@';
        }

        $port = $this->port !== null ? ':' . $this->port : '';

        return sprintf('%s://%s%s%s', $this->scheme, $userInfo, $this->host, $port);
    }
}
