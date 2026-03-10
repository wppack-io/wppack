<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DependencyInjection;

use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

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

        foreach ($builder->getDefinitions() as $definition) {
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
    }
}
