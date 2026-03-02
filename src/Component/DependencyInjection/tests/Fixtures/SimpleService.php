<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

final class SimpleService
{
    public function hello(): string
    {
        return 'hello';
    }
}
