<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Constant;

#[AsConfig]
final readonly class RequiredConstantConfig
{
    public function __construct(
        #[Constant('REQUIRED_CONSTANT_VALUE')]
        public string $value,
    ) {}
}
