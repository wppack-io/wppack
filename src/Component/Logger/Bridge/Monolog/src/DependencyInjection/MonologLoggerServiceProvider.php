<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Bridge\Monolog\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Logger\Bridge\Monolog\MonologHandler;
use WpPack\Component\Logger\Bridge\Monolog\MonologLoggerFactory;
use WpPack\Component\Logger\LoggerFactory;

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
