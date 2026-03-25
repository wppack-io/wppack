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

namespace WpPack\Component\Database;

final class SchemaManager
{
    /**
     * @param iterable<TableInterface> $tables
     */
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly iterable $tables = [],
    ) {}

    /**
     * Run dbDelta() for all registered tables.
     *
     * @return array<string, string> Results from dbDelta()
     */
    public function updateSchema(): array
    {
        $results = [];

        foreach ($this->tables as $table) {
            $results = array_merge($results, $this->updateTable($table));
        }

        return $results;
    }

    /**
     * Run dbDelta() for a single table.
     *
     * @return array<string, string> Results from dbDelta()
     */
    public function updateTable(TableInterface $table): array
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        return dbDelta($table->schema($this->db));
    }

    /**
     * Get all registered table schemas for debugging.
     *
     * @return list<string>
     */
    public function getSchemas(): array
    {
        $schemas = [];

        foreach ($this->tables as $table) {
            $schemas[] = $table->schema($this->db);
        }

        return $schemas;
    }
}
