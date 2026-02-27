<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Bridge\Monolog\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
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
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(LoggerFactory::class, MonologLoggerFactory::class)
            ->addArgument($this->handlers)
            ->addArgument($this->processors);
    }
}
