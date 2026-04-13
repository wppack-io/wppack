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

namespace WpPack\Component\Database\Platform;

use WpPack\Component\Database\DatabaseEngine;

final class SqlitePlatform extends AbstractPlatform
{
    public function getEngine(): DatabaseEngine
    {
        return DatabaseEngine::SQLite;
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
