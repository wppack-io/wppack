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

namespace WPPack\Component\DatabaseExport\RowTransformer;

use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\DatabaseExport\ExportConfiguration;

/**
 * Transforms wp_options rows during export.
 *
 * - Skips rows with excluded option name prefixes (transients, sessions)
 * - Resets active_plugins to empty serialized array
 * - Resets template/stylesheet to empty string
 * - Replaces table prefix in option_name values when configured
 */
final class WpOptionsTransformer implements RowTransformerInterface
{
    /**
     * Reserved option names that start with wp_ but should NOT be prefix-replaced.
     */
    private const RESERVED_OPTION_NAMES = [
        'wp_force_deactivated_plugins',
        'wp_page_for_privacy_policy',
    ];

    public function __construct(
        private readonly ExportConfiguration $config,
    ) {}

    public function supports(string $tableName): bool
    {
        return str_ends_with($tableName, '_options');
    }

    public function transform(array $row, TableSchema $schema): ?array
    {
        $optionName = $row['option_name'] ?? null;

        if ($optionName === null) {
            return $row;
        }

        // Skip rows matching excluded option prefixes
        foreach ($this->config->excludeOptionPrefixes as $prefix) {
            if (str_starts_with($optionName, $prefix)) {
                return null;
            }
        }

        // Reset active_plugins to empty serialized array
        if ($this->config->resetActivePlugins && $optionName === 'active_plugins') {
            $row['option_value'] = 'a:0:{}';
        }

        // Reset theme to empty string
        if ($this->config->resetTheme && ($optionName === 'template' || $optionName === 'stylesheet')) {
            $row['option_value'] = '';
        }

        // Replace table prefix in option_name values (e.g., wp_user_roles → WPPACK_PREFIX_user_roles)
        if ($this->config->replacePrefixInValues && $this->config->dbPrefix !== '' && str_starts_with($optionName, $this->config->dbPrefix)) {
            if (!\in_array($optionName, self::RESERVED_OPTION_NAMES, true)) {
                $row['option_name'] = $this->config->tablePrefix . substr($optionName, \strlen($this->config->dbPrefix));
            }
        }

        return $row;
    }
}
