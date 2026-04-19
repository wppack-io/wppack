<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\DatabaseExport;

final readonly class ExportConfiguration
{
    /**
     * Default option name prefixes to exclude from wp_options.
     *
     * Transients and session data are temporary and should not be included in exports.
     */
    public const DEFAULT_EXCLUDE_OPTION_PREFIXES = [
        '_transient_',
        '_site_transient_',
        '_wc_session_',
    ];

    /**
     * Default user meta keys to exclude from wp_usermeta.
     *
     * Session tokens are temporary authentication data that should not be exported.
     */
    public const DEFAULT_EXCLUDE_USER_META_KEYS = [
        'session_tokens',
    ];

    /**
     * @param string       $dbPrefix                 Actual database table prefix (e.g., 'wp_')
     * @param string       $tablePrefix              Prefix placeholder for table names in the output
     * @param list<string> $excludeTables            Table names (without prefix) to exclude
     * @param list<string> $includeTables            If non-empty, only these tables are exported
     * @param list<string> $additionalPrefixes       Additional table prefixes to include (e.g., ['wbk_', 'civicrm_'])
     * @param list<int>    $blogIds                  Target blog IDs (empty = all sites)
     * @param int          $batchSize                Rows per batch for reading
     * @param int          $transactionSize          Rows per transaction in SQL output
     * @param list<string> $excludeOptionPrefixes    Option name prefixes to exclude from wp_options
     * @param list<string> $excludeUserMetaKeys      User meta keys to exclude from wp_usermeta
     * @param bool         $resetActivePlugins       Set active_plugins to empty serialized array
     * @param bool         $resetTheme               Set template/stylesheet to empty string
     * @param bool         $replacePrefixInValues    Replace table prefix in option_name/meta_key values
     */
    public function __construct(
        public string $dbPrefix = '',
        public string $tablePrefix = 'WPPACK_PREFIX_',
        public array $excludeTables = [],
        public array $includeTables = [],
        public array $additionalPrefixes = [],
        public array $blogIds = [],
        public int $batchSize = 1000,
        public int $transactionSize = 1000,
        public array $excludeOptionPrefixes = self::DEFAULT_EXCLUDE_OPTION_PREFIXES,
        public array $excludeUserMetaKeys = self::DEFAULT_EXCLUDE_USER_META_KEYS,
        public bool $resetActivePlugins = true,
        public bool $resetTheme = true,
        public bool $replacePrefixInValues = true,
    ) {}
}
