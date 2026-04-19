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

namespace WPPack\Component\Debug\DependencyInjection;

use WPPack\Component\Debug\Attribute\AsDataCollector;
use WPPack\Component\Debug\ErrorHandler\RedirectHandler;
use WPPack\Component\Debug\Profiler\Profile;
use WPPack\Component\Debug\Toolbar\ToolbarSubscriber;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;

final class RegisterDataCollectorsPass implements CompilerPassInterface
{
    public const TAG = 'debug.data_collector';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(Profile::class)) {
            return;
        }

        $profileDefinition = $builder->findDefinition(Profile::class);

        // Collect all collectors with their priorities
        $collectors = [];

        foreach ($builder->all() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsDataCollector::class);

            $isCollector = $definition->hasTag(self::TAG) || $attributes !== [];

            if (!$isCollector) {
                continue;
            }

            $priority = 0;
            if ($attributes !== []) {
                $attr = $attributes[0]->newInstance();
                $priority = $attr->priority;
            }

            $collectors[] = [
                'id' => $definition->getId(),
                'priority' => $priority,
            ];
        }

        // Sort by priority descending (higher priority = first)
        usort($collectors, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);

        // Add each collector to Profile
        foreach ($collectors as $collector) {
            $profileDefinition->addMethodCall('addCollector', [new Reference($collector['id'])]);
        }

        // Inject collectors into ToolbarSubscriber and RedirectHandler
        $references = array_map(
            static fn(array $c): Reference => new Reference($c['id']),
            $collectors,
        );

        if ($builder->hasDefinition(ToolbarSubscriber::class)) {
            $builder->findDefinition(ToolbarSubscriber::class)
                ->setArgument('$collectors', $references);
        }

        if ($builder->hasDefinition(RedirectHandler::class)) {
            $builder->findDefinition(RedirectHandler::class)
                ->setArgument('$collectors', $references);
        }
    }
}
