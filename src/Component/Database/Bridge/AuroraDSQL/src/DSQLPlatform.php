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

namespace WPPack\Component\Database\Bridge\AuroraDSQL;

use WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLPlatform;

/**
 * Aurora DSQL platform — PostgreSQL compatible with DSQL-specific identity.
 */
class DSQLPlatform extends PostgreSQLPlatform
{
    public function getEngine(): string
    {
        return 'dsql';
    }
}
