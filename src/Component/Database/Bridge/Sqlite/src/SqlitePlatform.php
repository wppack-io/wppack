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

namespace WPPack\Component\Database\Bridge\Sqlite;

use WPPack\Component\Database\Platform\AbstractPlatform;

final class SqlitePlatform extends AbstractPlatform
{
    public function getEngine(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function getBeginTransactionSql(): string
    {
        return 'BEGIN';
    }

    public function getCharsetCollateSql(string $charset, string $collation): string
    {
        return '';
    }

    public function getAutoIncrementKeyword(): string
    {
        return 'AUTOINCREMENT';
    }
}
