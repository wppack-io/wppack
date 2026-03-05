<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\AutowireFixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class FloatEnvService
{
    public function __construct(
        #[Autowire(env: 'TEST_FLOAT')]
        public readonly float $value,
    ) {}
}
