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

namespace WpPack\Component\DatabaseExport\TableFilter;

use WpPack\Component\DatabaseExport\ExportConfiguration;

/**
 * Filters tables by WordPress prefix, multisite blog IDs, and additional prefixes.
 */
final class PrefixTableFilter implements TableFilterInterface
{
    /**
     * Global tables that are always included regardless of blog ID selection.
     */
    private const GLOBAL_TABLES = [
        'users',
        'usermeta',
        'blogs',
        'blog_versions',
        'site',
        'sitemeta',
        'signups',
        'registration_log',
        'sitecategories',
    ];

    public function __construct(
        private readonly string $dbPrefix,
        private readonly ExportConfiguration $config,
    ) {}

    public function filter(array $allTableNames): array
    {
        $matched = [];

        foreach ($allTableNames as $tableName) {
            if ($this->shouldInclude($tableName)) {
                $matched[] = $tableName;
            }
        }

        return $matched;
    }

    private function shouldInclude(string $tableName): bool
    {
        $suffix = $this->getTableSuffix($tableName);

        // Check additional prefixes (e.g., wbk_, civicrm_)
        if ($suffix === null) {
            foreach ($this->config->additionalPrefixes as $prefix) {
                if (str_starts_with($tableName, $prefix)) {
                    return $this->applyIncludeExclude($tableName);
                }
            }

            return false;
        }

        // Apply include/exclude filters
        if (!$this->applyIncludeExclude($suffix)) {
            return false;
        }

        // No blog ID restriction → include all
        if ($this->config->blogIds === []) {
            return true;
        }

        // Global tables are always included
        if (\in_array($suffix, self::GLOBAL_TABLES, true)) {
            return true;
        }

        // Determine which blog this table belongs to
        $blogId = $this->detectBlogId($suffix);

        return \in_array($blogId, $this->config->blogIds, true);
    }

    /**
     * Extract the table suffix after the database prefix.
     *
     * Returns null if the table does not start with the prefix.
     */
    private function getTableSuffix(string $tableName): ?string
    {
        if (!str_starts_with($tableName, $this->dbPrefix)) {
            return null;
        }

        return substr($tableName, \strlen($this->dbPrefix));
    }

    /**
     * Detect the blog ID from a table suffix.
     *
     * wp_posts        → blog 1 (main site)
     * wp_2_posts      → blog 2
     * wp_123_options   → blog 123
     */
    private function detectBlogId(string $suffix): int
    {
        if (preg_match('/^(\d+)_/', $suffix, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function applyIncludeExclude(string $name): bool
    {
        if ($this->config->includeTables !== [] && !\in_array($name, $this->config->includeTables, true)) {
            return false;
        }

        return !\in_array($name, $this->config->excludeTables, true);
    }
}
