<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService(lazy: true)]
final class LazyService
{
    public function compute(): int
    {
        return 42;
    }
}
