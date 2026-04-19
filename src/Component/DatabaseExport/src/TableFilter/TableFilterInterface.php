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

namespace WPPack\Component\DatabaseExport\TableFilter;

interface TableFilterInterface
{
    /**
     * Filter table names for export.
     *
     * @param list<string> $allTableNames All table names in the database
     *
     * @return list<string> Table names to export
     */
    public function filter(array $allTableNames): array;
}
