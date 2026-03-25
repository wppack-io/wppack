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

namespace WpPack\Component\Query\Enum;

enum TaxOperator: string
{
    case In = 'IN';
    case NotIn = 'NOT IN';
    case And = 'AND';
    case Exists = 'EXISTS';
    case NotExists = 'NOT EXISTS';
}
