<?php

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher\DependencyInjection;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\EventDispatcher\EventSubscriberInterface;

final class RegisterEventListenersPass implements CompilerPassInterface
{
    public const TAG = 'event_dispatcher.listener';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(EventDispatcher::class)) {
            return;
        }

        $dispatcherDefinition = $builder->findDefinition(EventDispatcher::class);

        foreach ($builder->getDefinitions() as $definition) {
            $class = $definition->getClass() ?? $definition->getId();

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            // Register #[AsEventListener] attributes
            $this->processAttributes($dispatcherDefinition, $definition, $reflection);

            // Register EventSubscriberInterface implementations
            if ($reflection->implementsInterface(EventSubscriberInterface::class)) {
                $dispatcherDefinition->addMethodCall('addSubscriber', [new Reference($definition->getId())]);
            }
        }
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function processAttributes(
        \WpPack\Component\DependencyInjection\Definition $dispatcherDefinition,
        \WpPack\Component\DependencyInjection\Definition $serviceDefinition,
        \ReflectionClass $reflection,
    ): void {
        // Class-level attributes
        foreach ($reflection->getAttributes(AsEventListener::class) as $attr) {
            /** @var AsEventListener $listener */
            $listener = $attr->newInstance();
            $method = $listener->method ?? '__invoke';
            $event = $listener->event ?? $this->resolveEventFromMethod($reflection, $method);

            if ($event === null) {
                continue;
            }

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
                $event = $listener->event ?? $this->resolveEventFromMethod($reflection, $method->getName());

                if ($event === null) {
                    continue;
                }

                $dispatcherDefinition->addMethodCall('addListener', [
                    $event,
                    [new Reference($serviceDefinition->getId()), $listener->method ?? $method->getName()],
                    $listener->priority,
                    $listener->acceptedArgs,
                ]);
            }
        }
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
