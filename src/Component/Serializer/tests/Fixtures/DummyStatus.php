<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

enum DummyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
