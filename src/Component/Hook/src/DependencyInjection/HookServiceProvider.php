<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;

final class HookServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(HookRegistry::class);
        $builder->register(HookDiscovery::class)
            ->addArgument(new Reference(HookRegistry::class));
    }
}
