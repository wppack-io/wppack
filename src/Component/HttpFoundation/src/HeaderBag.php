<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation;

class HeaderBag implements \Countable
{
    /** @var array<string, list<string>> */
    protected array $headers = [];

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $values) {
            $this->set($key, $values);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->headers;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->headers);
    }

    public function has(string $key): bool
    {
        return isset($this->headers[strtolower($key)]);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $key = strtolower($key);

        if (!isset($this->headers[$key]) || $this->headers[$key] === []) {
            return $default;
        }

        return $this->headers[$key][0];
    }

    /**
     * @param string|list<string> $values
     */
    public function set(string $key, string|array $values): void
    {
        $key = strtolower($key);

        if (\is_string($values)) {
            $values = [$values];
        }

        $this->headers[$key] = $values;
    }

    public function getDate(string $key, ?\DateTimeImmutable $default = null): ?\DateTimeImmutable
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        $date = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC7231, $value);

        if ($date === false) {
            return $default;
        }

        return $date;
    }

    public function count(): int
    {
        return \count($this->headers);
    }
}
