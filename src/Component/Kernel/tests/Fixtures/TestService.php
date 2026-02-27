<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel\Tests\Fixtures;

class TestService
{
    public function getValue(): string
    {
        return 'test';
    }
}
