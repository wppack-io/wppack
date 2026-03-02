<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Constant;

#[AsConfig]
final readonly class OptionalConstantConfig
{
    public function __construct(
        #[Constant('UNDEFINED_OPTIONAL_CONSTANT')]
        public string $value = 'fallback',
    ) {}
}
