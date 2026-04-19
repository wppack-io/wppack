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

class MysqlPlatform extends AbstractPlatform
{
    public function getEngine(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function getCharsetCollateSql(string $charset, string $collation): string
    {
        $sql = "DEFAULT CHARSET={$charset}";

        if ($collation !== '') {
            $sql .= " COLLATE={$collation}";
        }

        return $sql;
    }

    public function getAutoIncrementKeyword(): string
    {
        return 'AUTO_INCREMENT';
    }
}
