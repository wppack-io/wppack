<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures\Config;

use WpPack\Component\DependencyInjection\Attribute\Env;

final readonly class BoolConfig
{
    public function __construct(
        #[Env('TEST_ENV_BOOL')]
        public bool $value = true,
    ) {}
}
