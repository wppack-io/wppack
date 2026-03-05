<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\AutowireFixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class ParamAutowireService
{
    public function __construct(
        #[Autowire(param: 'some.param')]
        public readonly string $value,
    ) {}
}
