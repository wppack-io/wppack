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

namespace WPPack\Plugin\DebugPlugin\DependencyInjection;

use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\DependencyInjection\DebugServiceProvider;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Logger\DependencyInjection\LoggerServiceProvider;
use WPPack\Component\Logger\LoggerFactory;

final class DebugPluginServiceProvider implements ServiceProviderInterface
{
    private readonly DebugServiceProvider $debugServiceProvider;

    public function __construct()
    {
        $this->debugServiceProvider = new DebugServiceProvider();
    }

    public function register(ContainerBuilder $builder): void
    {
        // Logger is required — register services if not already present
        if (!$builder->hasDefinition(LoggerFactory::class)) {
            (new LoggerServiceProvider())->register($builder);
        }

        $this->debugServiceProvider->register($builder);

        // Ensure logger.debug channel exists for error handler logging
        if (!$builder->hasDefinition('logger.debug')) {
            $builder->register('logger.debug', \Psr\Log\LoggerInterface::class)
                ->setFactory([new Reference(LoggerFactory::class), 'create'])
                ->setArgument(0, 'debug');
        }

        // Override DebugConfig with enabled defaults for dev environment
        $builder->register(DebugConfig::class)
            ->addArgument(true)   // enabled
            ->addArgument(true);  // showToolbar
    }
}
