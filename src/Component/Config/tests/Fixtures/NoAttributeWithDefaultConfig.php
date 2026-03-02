<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;

#[AsConfig]
final readonly class NoAttributeWithDefaultConfig
{
    public function __construct(
        public string $name = 'default-name',
    ) {}
}
