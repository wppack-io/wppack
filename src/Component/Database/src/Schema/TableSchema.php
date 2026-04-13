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

namespace WpPack\Component\Database\Schema;

final readonly class TableSchema
{
    /**
     * @param list<ColumnSchema> $columns
     * @param list<string>|null  $primaryKey Column names forming the primary key (null = no PK)
     */
    public function __construct(
        public string $name,
        public string $createTableSql,
        public array $columns,
        public ?array $primaryKey = null,
    ) {}
}
