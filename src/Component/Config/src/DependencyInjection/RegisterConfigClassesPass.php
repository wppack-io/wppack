<?php

declare(strict_types=1);

namespace WpPack\Component\Config\DependencyInjection;

use WpPack\Component\Config\ConfigResolver;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class RegisterConfigClassesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        $taggedServices = $builder->findTaggedServiceIds('config.class');

        if ($taggedServices === []) {
            return;
        }

        if (!$builder->hasDefinition(ConfigResolver::class)) {
            $builder->register(ConfigResolver::class, ConfigResolver::class)
                ->setPublic(false);
        }

        foreach (array_keys($taggedServices) as $className) {
            $definition = $builder->findDefinition($className);
            $definition->setFactory([new Reference(ConfigResolver::class), 'resolve']);
            $definition->setArgument(0, $className);
        }
    }
}
