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
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Logger\ChannelResolver\ChannelResolverInterface;
use WPPack\Component\Logger\ChannelResolver\WordPressChannelResolver;
use WPPack\Component\Logger\ErrorHandler;
use WPPack\Component\Logger\ErrorLogInterceptor;
use WPPack\Component\Logger\Handler\ErrorLogHandler;
use WPPack\Component\Logger\LoggerFactory;

final class LoggerServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly string $defaultChannel = 'app',
        private readonly string $level = 'debug',
        private readonly bool $captureAllErrors = true,
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(ErrorLogHandler::class)
            ->addArgument($this->level);

        $builder->register(LoggerFactory::class)
            ->addArgument([new Reference(ErrorLogHandler::class)]);

        $builder->register(LoggerInterface::class, LoggerInterface::class)
            ->setFactory([new Reference(LoggerFactory::class), 'create'])
            ->addArgument($this->defaultChannel);

        $builder->register(WordPressChannelResolver::class);
        $builder->setAlias(ChannelResolverInterface::class, WordPressChannelResolver::class);

        $builder->register(ErrorHandler::class)
            ->addArgument(new Reference(LoggerFactory::class))
            ->addArgument(new Reference(ChannelResolverInterface::class))
            ->addArgument($this->captureAllErrors);

        $builder->register(ErrorLogInterceptor::class)
            ->setFactory([ErrorLogInterceptor::class, 'create']);
        if (ErrorLogInterceptor::getInstance() === null) {
            $builder->findDefinition(ErrorLogInterceptor::class)
                ->addMethodCall('register');
        }
        $builder->findDefinition(ErrorLogInterceptor::class)
            ->addMethodCall('setLoggerFactory', [new Reference(LoggerFactory::class)]);
    }
}
