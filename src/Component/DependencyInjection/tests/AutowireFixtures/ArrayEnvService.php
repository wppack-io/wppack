<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\AutowireFixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class ArrayEnvService
{
    public function __construct(
        #[Autowire(env: 'TEST_ARRAY')]
        public readonly array $value,
    ) {}
}
