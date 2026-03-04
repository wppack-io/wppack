<?php

declare(strict_types=1);

namespace WpPack\Component\Routing;

enum RoutePosition: string
{
    case Top = 'top';
    case Bottom = 'bottom';
}
