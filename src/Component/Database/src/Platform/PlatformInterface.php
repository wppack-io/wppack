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

namespace WPPack\Component\Database\Platform;

interface PlatformInterface
{
    /**
     * Engine identifier string (e.g., 'mysql', 'mariadb', 'sqlite', 'pgsql', 'dsql').
     *
     * Each Platform defines its own engine name. No central enum — Bridges are
     * free to introduce new engine identifiers without modifying core.
     */
    public function getEngine(): string;

    public function quoteIdentifier(string $identifier): string;

    public function getBeginTransactionSql(): string;

    public function getCharsetCollateSql(string $charset, string $collation): string;

    public function getAutoIncrementKeyword(): string;

    public function supportsNativePreparedStatements(): bool;
}
