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

namespace WPPack\Component\Mailer\DependencyInjection;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Mailer\Mailer;
use WPPack\Component\Mailer\Transport\NativeTransportFactory;
use WPPack\Component\Mailer\Transport\Transport;
use WPPack\Component\Mailer\Transport\TransportInterface;

final class MailerServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly string $dsn = 'native://default',
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(NativeTransportFactory::class)
            ->addTag('mailer.transport_factory');

        $builder->register(Transport::class)
            ->addArgument([]);

        $builder->register(TransportInterface::class)
            ->setFactory([new Reference(Transport::class), 'fromString'])
            ->addArgument($this->dsn);

        $builder->register(Mailer::class)
            ->addArgument(new Reference(TransportInterface::class));
    }
}
