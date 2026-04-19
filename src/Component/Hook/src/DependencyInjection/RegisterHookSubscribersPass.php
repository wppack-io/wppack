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

namespace WPPack\Component\Hook\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Hook\Attribute\AsHookSubscriber;
use WPPack\Component\Hook\HookDiscovery;
use WPPack\Component\Hook\HookRegistry;

final class RegisterHookSubscribersPass implements CompilerPassInterface
{
    public const TAG = 'hook.subscriber';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(HookDiscovery::class)) {
            return;
        }

        if (!$builder->hasDefinition(HookRegistry::class)) {
            return;
        }

        $discoveryDefinition = $builder->findDefinition(HookDiscovery::class);

        foreach ($builder->all() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            $isSubscriber = $definition->hasTag(self::TAG)
                || $reflection->getAttributes(AsHookSubscriber::class) !== [];

            if (!$isSubscriber) {
                continue;
            }

            $discoveryDefinition->addMethodCall('register', [new Reference($definition->getId())]);
        }

        $registryDefinition = $builder->findDefinition(HookRegistry::class);
        $registryDefinition->addMethodCall('register');
    }
}
