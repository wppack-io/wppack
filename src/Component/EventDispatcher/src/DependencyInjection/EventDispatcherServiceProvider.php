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

namespace WpPack\Component\EventDispatcher\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\EventDispatcher\EventDispatcher;

final class EventDispatcherServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(EventDispatcher::class);
        $builder->setAlias(EventDispatcherInterface::class, EventDispatcher::class);
    }
}
