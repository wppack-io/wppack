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

namespace WpPack\Component\HttpFoundation;

class ParameterBag implements \Countable
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(
        protected array $parameters = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->parameters);
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    public function set(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->parameters[$key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->parameters[$key] : $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return (string) $value;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    public function getBoolean(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }

    public function getAlpha(string $key, string $default = ''): string
    {
        $value = $this->getString($key, $default);

        return preg_replace('/[^a-zA-Z]/', '', $value) ?? '';
    }

    public function getAlnum(string $key, string $default = ''): string
    {
        $value = $this->getString($key, $default);

        return preg_replace('/[^a-zA-Z0-9]/', '', $value) ?? '';
    }

    public function count(): int
    {
        return \count($this->parameters);
    }
}
