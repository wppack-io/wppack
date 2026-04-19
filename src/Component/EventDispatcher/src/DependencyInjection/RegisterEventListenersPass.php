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

namespace WPPack\Component\EventDispatcher\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Definition;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\EventDispatcher\Attribute\AsEventListener;
use WPPack\Component\EventDispatcher\EventDispatcher;
use WPPack\Component\EventDispatcher\EventSubscriberInterface;

final class RegisterEventListenersPass implements CompilerPassInterface
{
    public const TAG = 'event_dispatcher.listener';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(EventDispatcher::class)) {
            return;
        }

        $dispatcherDefinition = $builder->findDefinition(EventDispatcher::class);

        foreach ($builder->all() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            // Register #[AsEventListener] attributes
            $hasAttributes = $this->processAttributes($dispatcherDefinition, $definition, $reflection);

            // Register EventSubscriberInterface implementations (skip if attributes were found to avoid double-registration)
            if (!$hasAttributes && $reflection->implementsInterface(EventSubscriberInterface::class)) {
                $dispatcherDefinition->addMethodCall('addSubscriber', [new Reference($definition->getId())]);
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function processAttributes(
        Definition $dispatcherDefinition,
        Definition $serviceDefinition,
        \ReflectionClass $reflection,
    ): bool {
        $found = false;

        // Class-level attributes
        foreach ($reflection->getAttributes(AsEventListener::class) as $attr) {
            /** @var AsEventListener $listener */
            $listener = $attr->newInstance();
            $method = $listener->method ?? '__invoke';
            $event = $listener->event ?? $this->resolveEventFromMethod($reflection, $method);

            if ($event === null) {
                continue;
            }

            $found = true;
            $dispatcherDefinition->addMethodCall('addListener', [
                $event,
                [new Reference($serviceDefinition->getId()), $method],
                $listener->priority,
                $listener->acceptedArgs,
            ]);
        }

        // Method-level attributes
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes(AsEventListener::class) as $attr) {
                /** @var AsEventListener $listener */
                $listener = $attr->newInstance();
                $callbackMethod = $listener->method ?? $method->getName();
                $event = $listener->event ?? $this->resolveEventFromMethod($reflection, $callbackMethod);

                if ($event === null) {
                    continue;
                }

                $found = true;
                $dispatcherDefinition->addMethodCall('addListener', [
                    $event,
                    [new Reference($serviceDefinition->getId()), $callbackMethod],
                    $listener->priority,
                    $listener->acceptedArgs,
                ]);
            }
        }

        return $found;
    }

    /**
     * Resolve the event class from the first parameter type hint of the method.
     *
     * @param \ReflectionClass<object> $reflection
     */
    private function resolveEventFromMethod(\ReflectionClass $reflection, string $methodName): ?string
    {
        if (!$reflection->hasMethod($methodName)) {
            return null;
        }

        $method = $reflection->getMethod($methodName);
        $parameters = $method->getParameters();

        if ($parameters === []) {
            return null;
        }

        $type = $parameters[0]->getType();

        if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
            return $type->getName();
        }

        return null;
    }
}
