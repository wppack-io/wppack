<?php

declare(strict_types=1);

namespace WpPack\Component\Hook;

enum HookType: string
{
    case Action = 'action';
    case Filter = 'filter';
}
