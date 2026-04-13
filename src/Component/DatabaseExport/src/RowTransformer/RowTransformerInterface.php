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

interface RowTransformerInterface
{
    public function supports(string $tableName): bool;

    /**
     * Transform a row before export.
     *
     * Return null to skip the row entirely.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    public function transform(array $row, TableSchema $schema): ?array;
}
