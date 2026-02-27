<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\AsAlias;
use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService]
#[AsAlias(id: SampleInterface::class)]
final class SampleImplementation implements SampleInterface
{
    public function getValue(): string
    {
        return 'sample';
    }
}
