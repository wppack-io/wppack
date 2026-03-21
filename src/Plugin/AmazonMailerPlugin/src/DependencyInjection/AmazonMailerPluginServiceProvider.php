<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;
use WpPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler;
use WpPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesNotificationNormalizer;

final class AmazonMailerPluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Configuration
        $builder->register(AmazonMailerConfiguration::class)
            ->setFactory([AmazonMailerConfiguration::class, 'fromEnvironment']);

        // Transport Factories
        $builder->register(SesTransportFactory::class)
            ->addTag('mailer.transport_factory');

        $builder->register(NativeTransportFactory::class)
            ->addTag('mailer.transport_factory');

        // Transport router (factories injected by RegisterTransportFactoriesPass)
        $builder->register(Transport::class)
            ->addArgument([]);

        // TransportInterface (resolved from DSN)
        $builder->register(TransportInterface::class)
            ->setFactory([new Reference(Transport::class), 'fromString'])
            ->addArgument(new Reference(AmazonMailerConfiguration::class . '.dsn'));

        // DSN string extraction from Configuration
        $builder->register(AmazonMailerConfiguration::class . '.dsn')
            ->setClass('string')
            ->setFactory([self::class, 'extractDsn'])
            ->addArgument(new Reference(AmazonMailerConfiguration::class));

        // Mailer
        $builder->register(Mailer::class)
            ->addArgument(new Reference(TransportInterface::class));

        // Message normalizer
        $builder->register(SesNotificationNormalizer::class);

        // Message handlers
        $builder->register(BounceHandler::class)
            ->addTag('messenger.message_handler');

        $builder->register(ComplaintHandler::class)
            ->addTag('messenger.message_handler');
    }

    public static function extractDsn(AmazonMailerConfiguration $config): string
    {
        return $config->dsn;
    }
}
