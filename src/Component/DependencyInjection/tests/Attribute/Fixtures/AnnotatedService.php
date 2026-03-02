<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Attribute\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class AnnotatedService
{
    public function __construct(
        #[Autowire(env: 'APP_ENV')]
        public readonly string $env,
        #[Autowire(param: 'app.debug')]
        public readonly string $debug,
    ) {}
}
