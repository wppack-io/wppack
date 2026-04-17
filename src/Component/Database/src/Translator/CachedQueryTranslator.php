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

namespace WpPack\Component\Database\Translator;

/**
 * Memoising decorator for QueryTranslatorInterface.
 *
 * Re-running the phpmyadmin/sql-parser parse + AST-guided rewrite for
 * identical SQL is the translator's biggest per-call cost. Long-running
 * WP-CLI workers and high-traffic web requests often see the same SQL
 * shapes repeatedly (same SELECT template with different bound params —
 * remember, placeholders are stripped to `?` before we translate). Wrap
 * the real translator in this decorator to get a bounded LRU cache
 * keyed by SQL string.
 *
 * Translator exceptions (parser failures, unsupported features) are
 * cached too so a batch of identical bad queries doesn't burn parse
 * cycles — the cached exception is rethrown each time.
 */
final class CachedQueryTranslator implements QueryTranslatorInterface
{
    /** @var array<string, list<string>|\Throwable> */
    private array $cache = [];

    /** @var int Maximum number of distinct SQL strings to remember. */
    private readonly int $capacity;

    public function __construct(
        private readonly QueryTranslatorInterface $inner,
        int $capacity = 256,
    ) {
        $this->capacity = max(1, $capacity);
    }

    public function translate(string $sql): array
    {
        if (\array_key_exists($sql, $this->cache)) {
            // Bump to the tail (LRU recency): remove + re-insert.
            $hit = $this->cache[$sql];
            unset($this->cache[$sql]);
            $this->cache[$sql] = $hit;

            if ($hit instanceof \Throwable) {
                throw $hit;
            }

            return $hit;
        }

        try {
            $result = $this->inner->translate($sql);
        } catch (\Throwable $e) {
            $this->store($sql, $e);

            throw $e;
        }

        $this->store($sql, $result);

        return $result;
    }

    /**
     * @param list<string>|\Throwable $value
     */
    private function store(string $sql, array|\Throwable $value): void
    {
        if (\count($this->cache) >= $this->capacity) {
            // Evict the least-recently-used entry (first in insertion
            // order after the bump-on-hit rewrite above).
            array_shift($this->cache);
        }

        $this->cache[$sql] = $value;
    }

    /**
     * Drop every cached entry. Callers that mutate schema outside the
     * translator's awareness (migrations, new plugin activation) reach
     * for this to avoid stale results from the constraint / rewrite
     * logic baked into $inner.
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    public function size(): int
    {
        return \count($this->cache);
    }
}
