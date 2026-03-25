<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
