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

namespace WpPack\Component\DatabaseExport;

final readonly class ExportConfiguration
{
    /**
     * @param string       $tablePrefix           Prefix placeholder for table names in the output
     * @param list<string> $excludeTables         Table names (without prefix) to exclude
     * @param list<string> $includeTables         If non-empty, only these tables are exported
     * @param list<string> $additionalPrefixes    Additional table prefixes to include (e.g., ['wbk_', 'civicrm_'])
     * @param list<int>    $blogIds               Target blog IDs (empty = all sites)
     * @param int          $batchSize             Rows per batch for reading
     * @param int          $transactionSize       Rows per transaction in SQL output
     * @param list<string> $excludeOptionPrefixes Option name prefixes to exclude from wp_options
     * @param bool         $resetActivePlugins    Set active_plugins to empty serialized array
     * @param bool         $resetTheme            Set template/stylesheet to empty string
     */
    public function __construct(
        public string $tablePrefix = 'WPPACK_PREFIX_',
        public array $excludeTables = [],
        public array $includeTables = [],
        public array $additionalPrefixes = [],
        public array $blogIds = [],
        public int $batchSize = 1000,
        public int $transactionSize = 1000,
        public array $excludeOptionPrefixes = [],
        public bool $resetActivePlugins = true,
        public bool $resetTheme = true,
    ) {}
}
