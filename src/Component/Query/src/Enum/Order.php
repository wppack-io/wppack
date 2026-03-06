<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Enum;

enum Order: string
{
    case Asc = 'ASC';
    case Desc = 'DESC';
}
