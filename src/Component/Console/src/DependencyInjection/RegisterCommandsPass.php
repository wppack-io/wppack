<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Console\DependencyInjection;

use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\CommandRegistry;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class RegisterCommandsPass implements CompilerPassInterface
{
    public const TAG = 'console.command';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(CommandRegistry::class)) {
            return;
        }

        $registryDefinition = $builder->findDefinition(CommandRegistry::class);

        foreach ($builder->all() as $definition) {
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
