<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures\Config;

use WpPack\Component\DependencyInjection\Attribute\Constant;

final readonly class ConstantConfig
{
    public function __construct(
        #[Constant('TEST_CONSTANT_VALUE')]
        public int $value = 0,
    ) {}
}
