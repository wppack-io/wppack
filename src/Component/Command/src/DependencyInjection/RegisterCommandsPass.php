<?php

declare(strict_types=1);

namespace WpPack\Component\Command\DependencyInjection;

use WpPack\Component\Command\AbstractCommand;
use WpPack\Component\Command\Attribute\AsCommand;
use WpPack\Component\Command\CommandRegistry;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class RegisterCommandsPass implements CompilerPassInterface
{
    public const TAG = 'command.command';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(CommandRegistry::class)) {
            return;
        }

        $registryDefinition = $builder->findDefinition(CommandRegistry::class);

        foreach ($builder->getDefinitions() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            if (!is_subclass_of($class, AbstractCommand::class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            $isCommand = $definition->hasTag(self::TAG)
                || $reflection->getAttributes(AsCommand::class) !== [];

            if (!$isCommand) {
                continue;
            }

            $registryDefinition->addMethodCall('add', [new Reference($definition->getId())]);
        }

        $registryDefinition->addMethodCall('register');
    }
}
