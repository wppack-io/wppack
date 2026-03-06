<?php

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
