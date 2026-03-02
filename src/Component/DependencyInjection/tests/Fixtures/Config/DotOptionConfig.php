<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures\Config;

use WpPack\Component\DependencyInjection\Attribute\Option;

final readonly class DotOptionConfig
{
    public function __construct(
        #[Option('test_settings.nested.key')]
        public string $value = '',
    ) {}
}
