<?php

declare(strict_types=1);

namespace WpPack\Component\Storage;

enum Visibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}
