<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\AutowireFixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class EnvNoDefaultService
{
    public function __construct(
        #[Autowire(env: 'UNDEFINED_ENV_VAR')]
        public readonly string $value,
    ) {}
}
