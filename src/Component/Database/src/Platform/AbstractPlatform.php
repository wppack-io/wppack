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

abstract class AbstractPlatform implements PlatformInterface
{
    public function getBeginTransactionSql(): string
    {
        return 'START TRANSACTION';
    }

    public function supportsNativePreparedStatements(): bool
    {
        return true;
    }
}
