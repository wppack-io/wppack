<?php

declare(strict_types=1);

namespace WpPack\Component\Command\DependencyInjection;

use WpPack\Component\Command\CommandRegistry;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

final class CommandServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(CommandRegistry::class);
    }
}
