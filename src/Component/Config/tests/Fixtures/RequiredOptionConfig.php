<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Option;

#[AsConfig]
final readonly class RequiredOptionConfig
{
    public function __construct(
        #[Option('required_option')]
        public string $value,
    ) {}
}
