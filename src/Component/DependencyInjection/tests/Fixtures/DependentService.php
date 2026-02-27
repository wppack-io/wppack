<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService]
final class DependentService
{
    public function __construct(
        public readonly SimpleService $simple,
    ) {}
}
