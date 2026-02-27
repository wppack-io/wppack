<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService]
final class SimpleService
{
    public function hello(): string
    {
        return 'hello';
    }
}
