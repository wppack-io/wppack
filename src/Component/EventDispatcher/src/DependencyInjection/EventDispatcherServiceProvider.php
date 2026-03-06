<?php

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
