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

namespace WPPack\Component\Messenger\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Messenger\Attribute\AsMessageHandler;
use WPPack\Component\Messenger\Handler\HandlerLocator;

/**
 * Collects services tagged with 'messenger.message_handler' and registers
 * them in the HandlerLocator.
 *
 * Each handler's __invoke() method type-hint determines which message
 * class it handles. The #[AsMessageHandler] attribute's `handles` property
 * can override this.
 */
final class RegisterMessageHandlersPass implements CompilerPassInterface
{
    public const TAG = 'messenger.message_handler';

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(HandlerLocator::class)) {
            return;
        }

        $locatorDefinition = $builder->findDefinition(HandlerLocator::class);

        foreach ($builder->findTaggedServiceIds(self::TAG) as $serviceId => $tags) {
            $definition = $builder->findDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                continue;
            }

            $messageClass = $this->resolveMessageClass($class);

            if ($messageClass === null) {
                continue;
            }

            $locatorDefinition->addMethodCall('addHandler', [
                $messageClass,
                [new Reference($serviceId), '__invoke'],
            ]);
        }
    }

    /**
     * @param class-string $handlerClass
     */
    private function resolveMessageClass(string $handlerClass): ?string
    {
        $reflection = new \ReflectionClass($handlerClass);

        // Check #[AsMessageHandler] attribute for explicit `handles`
        $attributes = $reflection->getAttributes(AsMessageHandler::class);
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance->handles !== null) {
                return $instance->handles;
            }
        }

        // Resolve from __invoke() parameter type-hint
        if (!$reflection->hasMethod('__invoke')) {
            return null;
        }

        $params = $reflection->getMethod('__invoke')->getParameters();

        if ($params === []) {
            return null;
        }

        $type = $params[0]->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }
}
