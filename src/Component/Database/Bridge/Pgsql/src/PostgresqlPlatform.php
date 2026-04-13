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

namespace WpPack\Component\Database\Bridge\Pgsql;

use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Database\Platform\AbstractPlatform;

class PostgresqlPlatform extends AbstractPlatform
{
    public function getEngine(): DatabaseEngine
    {
        return DatabaseEngine::PostgreSQL;
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
        return 'SERIAL';
    }
}
