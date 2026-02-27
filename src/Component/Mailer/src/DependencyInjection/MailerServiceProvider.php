<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;

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
