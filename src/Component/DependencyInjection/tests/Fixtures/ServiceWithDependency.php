<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class ServiceWithDependency
{
    public function __construct(
        #[Autowire(service: 'custom.service')]
        public readonly SimpleService $service,
    ) {}
}
