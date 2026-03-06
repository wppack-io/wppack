<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Enum;

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
