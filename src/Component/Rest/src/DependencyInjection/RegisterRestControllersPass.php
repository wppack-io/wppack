<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\DependencyInjection;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\RestRegistry;

final class RegisterRestControllersPass implements CompilerPassInterface
{
    public const TAG = 'rest.controller';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(RestRegistry::class)) {
            return;
        }

        $registryDefinition = $builder->findDefinition(RestRegistry::class);

        foreach ($builder->getDefinitions() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            $isController = $definition->hasTag(self::TAG)
                || $reflection->getAttributes(RestRoute::class) !== [];

            if (!$isController) {
                continue;
            }

            $registryDefinition->addMethodCall('register', [new Reference($definition->getId())]);
        }
    }
}
