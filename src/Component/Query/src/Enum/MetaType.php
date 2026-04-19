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

namespace WPPack\Component\Query\Enum;

enum MetaType: string
{
    case Numeric = 'NUMERIC';
    case Binary = 'BINARY';
    case Char = 'CHAR';
    case Date = 'DATE';
    case DateTime = 'DATETIME';
    case Decimal = 'DECIMAL';
    case Signed = 'SIGNED';
    case Time = 'TIME';
    case Unsigned = 'UNSIGNED';
}
