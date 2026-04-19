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

namespace WPPack\Component\Rest\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\RestRegistry;

final class RegisterRestControllersPass implements CompilerPassInterface
{
    public const TAG = 'rest.controller';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(RestRegistry::class)) {
            return;
        }

        $registryDefinition = $builder->findDefinition(RestRegistry::class);

        foreach ($builder->all() as $definition) {
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
