<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\DependencyInjection\Tests\Fixtures;

use WPPack\Component\DependencyInjection\Attribute\Autowire;

final class ServiceWithDependency
{
    public function __construct(
        #[Autowire(service: 'custom.service')]
        public readonly SimpleService $service,
    ) {}
}
