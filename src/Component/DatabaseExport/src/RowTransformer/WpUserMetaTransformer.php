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
 * Transforms wp_usermeta rows during export.
 *
 * - Skips rows with excluded meta keys (e.g., session_tokens)
 * - Replaces table prefix in meta_key values when configured
 */
final class WpUserMetaTransformer implements RowTransformerInterface
{
    public function __construct(
        private readonly ExportConfiguration $config,
        private readonly string $dbPrefix = '',
    ) {}

    public function supports(string $tableName): bool
    {
        return str_ends_with($tableName, '_usermeta');
    }

    public function transform(array $row, TableSchema $schema): ?array
    {
        $metaKey = $row['meta_key'] ?? null;

        if ($metaKey === null) {
            return $row;
        }

        // Skip rows with excluded meta keys
        if (\in_array($metaKey, $this->config->excludeUserMetaKeys, true)) {
            return null;
        }

        // Replace table prefix in meta_key values (e.g., wp_capabilities → WPPACK_PREFIX_capabilities)
        if ($this->config->replacePrefixInValues && $this->dbPrefix !== '' && str_starts_with($metaKey, $this->dbPrefix)) {
            $row['meta_key'] = $this->config->tablePrefix . substr($metaKey, \strlen($this->dbPrefix));
        }

        return $row;
    }
}
