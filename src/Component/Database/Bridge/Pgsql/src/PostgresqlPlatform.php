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
        // Preserve the identifier's original case. PostgreSQL folds unquoted
        // identifiers to lowercase, but our callers — including WP and the
        // query translator — are expected to give us names whose case matches
        // what's actually stored in the catalog, so forcing a lowercase
        // transform here breaks any mixed-case identifier the caller passes.
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
