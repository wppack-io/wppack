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

namespace WpPack\Component\Storage\StreamWrapper;

final class StatCache
{
    /** @var array<string, array<string|int, int|string>> */
    private array $cache = [];

    /** @var list<string> */
    private array $keys = [];

    public function __construct(
        private readonly int $maxSize = 1000,
    ) {}

    /**
     * @return array<string|int, int|string>|null
     */
    public function get(string $path): ?array
    {
        return $this->cache[$path] ?? null;
    }

    /**
     * @param array<string|int, int|string> $stat
     */
    public function set(string $path, array $stat): void
    {
        if (isset($this->cache[$path])) {
            $this->cache[$path] = $stat;

            return;
        }

        if (\count($this->keys) >= $this->maxSize) {
            $evict = array_shift($this->keys);
            unset($this->cache[$evict]);
        }

        $this->cache[$path] = $stat;
        $this->keys[] = $path;
    }

    public function remove(string $path): void
    {
        if (!isset($this->cache[$path])) {
            return;
        }

        unset($this->cache[$path]);
        $this->keys = array_values(array_filter(
            $this->keys,
            static fn(string $key): bool => $key !== $path,
        ));
    }

    public function clear(): void
    {
        $this->cache = [];
        $this->keys = [];
    }
}
