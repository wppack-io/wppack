<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures\Config;

use WpPack\Component\DependencyInjection\Attribute\Env;

final readonly class TypeCastConfig
{
    public function __construct(
        #[Env('TEST_ENV_INT')]
        public int $intValue = 0,
        #[Env('TEST_ENV_INT')]
        public bool $boolValue = false,
    ) {}
}
