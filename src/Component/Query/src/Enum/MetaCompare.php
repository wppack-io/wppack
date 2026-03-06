<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Enum;

enum MetaCompare: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case In = 'IN';
    case NotIn = 'NOT IN';
    case Between = 'BETWEEN';
    case NotBetween = 'NOT BETWEEN';
    case Exists = 'EXISTS';
    case NotExists = 'NOT EXISTS';
    case RegExp = 'REGEXP';
    case NotRegExp = 'NOT REGEXP';
}
