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
use WpPack\Component\Option\OptionManager;
use WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;
use WpPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler;
use WpPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesNotificationNormalizer;
use WpPack\Plugin\AmazonMailerPlugin\SuppressionList;

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

        // Mailer
        $builder->register(Mailer::class)
            ->setFactory([self::class, 'createMailer'])
            ->addArgument(new Reference(AmazonMailerConfiguration::class));

        // Message normalizer
        $builder->register(SesNotificationNormalizer::class);

        // Option manager
        $builder->register(OptionManager::class);

        // Suppression list
        $builder->register(SuppressionList::class)
            ->addArgument(new Reference(OptionManager::class));

        // Message handlers
        $builder->register(BounceHandler::class)
            ->addArgument(new Reference(SuppressionList::class))
            ->addTag('messenger.message_handler');

        $builder->register(ComplaintHandler::class)
            ->addArgument(new Reference(SuppressionList::class))
            ->addTag('messenger.message_handler');
    }

    public static function createMailer(AmazonMailerConfiguration $config): Mailer
    {
        return new Mailer(transport: $config->dsn);
    }
}
