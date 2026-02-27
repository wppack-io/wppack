<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\DependencyInjection\Fixtures;

final class NoConstructorService
{
    public string $value = 'default';
}
