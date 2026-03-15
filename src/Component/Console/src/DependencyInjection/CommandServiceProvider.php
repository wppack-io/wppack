<?php

declare(strict_types=1);

namespace WpPack\Component\Console\DependencyInjection;

use WpPack\Component\Console\CommandRegistry;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

final class CommandServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(CommandRegistry::class);
    }
}
