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

interface AdapterInterface
{
    public function getName(): string;

    public function get(string $key): ?string;

    /**
     * @param list<string> $keys
     * @return array<string, ?string>
     */
    public function getMultiple(array $keys): array;

    public function set(string $key, string $value, int $ttl = 0): bool;

    /**
     * @param array<string, string> $values
     * @return array<string, bool>
     */
    public function setMultiple(array $values, int $ttl = 0): array;

    public function add(string $key, string $value, int $ttl = 0): bool;

    public function delete(string $key): bool;

    /**
     * @param list<string> $keys
     * @return array<string, bool>
     */
    public function deleteMultiple(array $keys): array;

    public function increment(string $key, int $offset = 1): ?int;

    public function decrement(string $key, int $offset = 1): ?int;

    public function flush(string $prefix = ''): bool;

    public function isAvailable(): bool;

    public function close(): void;
}
