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

namespace WpPack\Component\Database\Placeholder;

/**
 * Per-request storage mapping a deterministic id to the params array of a single
 * wpdb::prepare() call. WpPackWpdb uses this to keep values out of the returned
 * SQL string while still delivering them to the driver at query() execution time
 * as native prepared-statement parameters.
 *
 * id format: 12 lowercase hex characters derived from sha1($sql . "\x01" . serialize($params)).
 * Marker format: "/*WPP:<id>*\/" (SQL block comment, ignored by every engine).
 */
final class PreparedBank
{
    private const MARKER_PREFIX = '/*WPP:';
    private const MARKER_SUFFIX = '*/';
    public const MARKER_PATTERN = '#/\*WPP:([a-f0-9]{12})\*/#';

    /** @var array<string, list<mixed>> id → params */
    private array $entries = [];

    /**
     * Compute the deterministic id for the given (sql, params) pair.
     *
     * @param list<mixed> $params
     */
    public function idFor(string $sql, array $params): string
    {
        return substr(sha1($sql . "\x01" . serialize($params)), 0, 12);
    }

    /**
     * Build the SQL comment marker for the given id.
     */
    public function markerFor(string $id): string
    {
        return self::MARKER_PREFIX . $id . self::MARKER_SUFFIX;
    }

    /**
     * Store params under the given id. Repeated calls with the same id overwrite
     * with identical values (the id is derived from sql+params so entries are
     * idempotent).
     *
     * @param list<mixed> $params
     */
    public function store(string $id, array $params): void
    {
        $this->entries[$id] = $params;
    }

    /**
     * Extract and remove the params for all markers present in the given SQL,
     * in the order they appear. Unknown ids are silently skipped.
     *
     * @return array{0: string, 1: list<mixed>} [sqlWithMarkersRemoved, flattenedParams]
     */
    public function consume(string $sql): array
    {
        if (!str_contains($sql, self::MARKER_PREFIX)) {
            return [$sql, []];
        }

        $params = [];

        if (preg_match_all(self::MARKER_PATTERN, $sql, $matches) > 0) {
            foreach ($matches[1] as $id) {
                if (isset($this->entries[$id])) {
                    foreach ($this->entries[$id] as $value) {
                        $params[] = $value;
                    }

                    unset($this->entries[$id]);
                }
            }
        }

        $cleanSql = (string) preg_replace(self::MARKER_PATTERN, '', $sql);

        return [$cleanSql, $params];
    }

    /**
     * Drop all remaining entries (orphans from prepare() calls that were never
     * followed by a query()). Called from WpPackWpdb::resetPreparedBank().
     */
    public function reset(): void
    {
        $this->entries = [];
    }

    /**
     * For unit tests / debug.
     */
    public function size(): int
    {
        return \count($this->entries);
    }
}
