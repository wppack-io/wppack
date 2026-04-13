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

interface PlatformInterface
{
    public function getEngine(): DatabaseEngine;

    public function quoteIdentifier(string $identifier): string;

    public function getBeginTransactionSql(): string;

    public function getCharsetCollateSql(string $charset, string $collation): string;

    public function getAutoIncrementKeyword(): string;

    public function supportsNativePreparedStatements(): bool;
}
