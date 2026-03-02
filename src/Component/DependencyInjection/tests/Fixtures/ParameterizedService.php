<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class ParameterizedService
{
    public function __construct(
        #[Autowire(param: 'app.name')]
        public readonly string $appName,
        #[Autowire(env: 'APP_DEBUG')]
        public readonly string $debug,
    ) {}
}
