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

namespace WPPack\Component\Database\Bridge\Pgsql;

use WPPack\Component\Database\Platform\AbstractPlatform;

class PostgresqlPlatform extends AbstractPlatform
{
    public function getEngine(): string
    {
        return 'pgsql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        // PostgreSQL folds unquoted identifiers to lowercase. WordPress uses
        // mixed-case names (e.g. ID, post_title) in both DDL and DML without
        // quoting, so they are resolved to lowercase. Quoted identifiers are
        // case-sensitive, so we must lowercase them to match.
        return '"' . str_replace('"', '""', strtolower($identifier)) . '"';
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
