<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

enum DummyIntEnum: int
{
    case Low = 1;
    case High = 10;
}
