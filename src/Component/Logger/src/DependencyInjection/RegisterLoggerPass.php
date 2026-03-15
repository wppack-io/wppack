<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\DependencyInjection;

use Psr\Log\LoggerInterface;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Logger\Attribute\LoggerChannel;
use WpPack\Component\Logger\LoggerFactory;

final class RegisterLoggerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(LoggerFactory::class)) {
            return;
        }

        foreach ($builder->getDefinitions() as $id => $definition) {
            $class = $definition->getClass() ?? $id;

            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $constructor = $reflection->getConstructor();

            if ($constructor === null) {
                continue;
            }

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if (!$type instanceof \ReflectionNamedType || $type->getName() !== LoggerInterface::class) {
                    continue;
                }

                $attributes = $parameter->getAttributes(LoggerChannel::class);

                if ($attributes === []) {
                    continue;
                }

                /** @var LoggerChannel $loggerChannel */
                $loggerChannel = $attributes[0]->newInstance();
                $channel = $loggerChannel->channel;
                $serviceId = 'logger.' . $channel;

                if (!$builder->hasDefinition($serviceId)) {
                    $builder->register($serviceId)
                        ->setFactory([new Reference(LoggerFactory::class), 'create'])
                        ->setArgument(0, $channel);
                }

                $definition->setArgument($parameter->getPosition(), new Reference($serviceId));
            }
        }
    }
}
