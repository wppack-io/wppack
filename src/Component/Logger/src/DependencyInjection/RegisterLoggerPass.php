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

namespace WPPack\Component\Logger\DependencyInjection;

use Psr\Log\LoggerInterface;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Logger\Attribute\LoggerChannel;
use WPPack\Component\Logger\LoggerFactory;

final class RegisterLoggerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(LoggerFactory::class)) {
            return;
        }

        foreach ($builder->all() as $id => $definition) {
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
                    $builder->register($serviceId, LoggerInterface::class)
                        ->setFactory([new Reference(LoggerFactory::class), 'create'])
                        ->setArgument(0, $channel);
                }

                $definition->setArgument($parameter->getPosition(), new Reference($serviceId));
            }
        }
    }
}
