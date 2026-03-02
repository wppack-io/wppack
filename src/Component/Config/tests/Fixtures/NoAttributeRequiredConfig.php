<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;

#[AsConfig]
final readonly class NoAttributeRequiredConfig
{
    public function __construct(
        public string $name,
    ) {}
}
