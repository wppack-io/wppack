<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\DependencyInjection;

use Psr\Log\LoggerInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Logger\Handler\ErrorLogHandler;
use WpPack\Component\Logger\LoggerFactory;

final class LoggerServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly string $defaultChannel = 'app',
        private readonly string $level = 'debug',
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(ErrorLogHandler::class)
            ->addArgument($this->level);

        $builder->register(LoggerFactory::class)
            ->addArgument([new Reference(ErrorLogHandler::class)]);

        $builder->register(LoggerInterface::class)
            ->setFactory([new Reference(LoggerFactory::class), 'create'])
            ->addArgument($this->defaultChannel);
    }
}
