<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\DependencyInjection;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;

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

        foreach ($builder->getDefinitions() as $definition) {
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
        $registryDefinition->addMethodCall('bind');
    }
}
