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

namespace WPPack\Component\Logger\Bridge\Monolog\DependencyInjection;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WPPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WPPack\Component\Logger\LoggerFactory;

final class MonologLoggerServiceProvider implements ServiceProviderInterface
{
    /**
     * @param \Monolog\Handler\HandlerInterface[]  $handlers
     * @param \Monolog\Processor\ProcessorInterface[] $processors
     */
    public function __construct(
        private readonly array $handlers = [],
        private readonly array $processors = [],
        private readonly string $level = 'debug',
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(MonologLoggerFactory::class)
            ->addArgument($this->handlers)
            ->addArgument($this->processors);

        $builder->register(MonologHandler::class)
            ->addArgument(new Reference(MonologLoggerFactory::class))
            ->addArgument($this->level);

        if (!$builder->hasDefinition(LoggerFactory::class)) {
            throw new \LogicException(sprintf(
                '%s requires LoggerServiceProvider to be registered first.',
                self::class,
            ));
        }

        $builder->findDefinition(LoggerFactory::class)
            ->setArgument(0, [new Reference(MonologHandler::class)]);
    }
}
