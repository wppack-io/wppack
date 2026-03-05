<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\AutowireFixtures;

use WpPack\Component\DependencyInjection\Attribute\Autowire;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

final class ServiceAutowireService
{
    public function __construct(
        #[Autowire(service: 'some.service')]
        public readonly SimpleService $service,
    ) {}
}
