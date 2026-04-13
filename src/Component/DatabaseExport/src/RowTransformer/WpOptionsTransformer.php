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

namespace WpPack\Component\DatabaseExport\RowTransformer;

use WpPack\Component\Database\Schema\TableSchema;
use WpPack\Component\DatabaseExport\ExportConfiguration;

/**
 * Transforms wp_options rows during export.
 *
 * - Resets active_plugins to empty serialized array
 * - Resets template/stylesheet to empty string
 * - Skips rows with excluded option name prefixes
 */
final class WpOptionsTransformer implements RowTransformerInterface
{
    private readonly string $optionsTableSuffix;

    public function __construct(
        private readonly ExportConfiguration $config,
    ) {
        $this->optionsTableSuffix = 'options';
    }

    public function supports(string $tableName): bool
    {
        return str_ends_with($tableName, '_' . $this->optionsTableSuffix);
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

        return $row;
    }
}
