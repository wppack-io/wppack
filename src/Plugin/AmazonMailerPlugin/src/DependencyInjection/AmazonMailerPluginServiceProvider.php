<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WpPack\Component\Messenger\Handler\HandlerLocator;
use WpPack\Component\Messenger\MessageBus;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Messenger\Middleware\HandleMessageMiddleware;
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
        // Messenger (synchronous fallback if no dedicated Messenger plugin)
        if (!$builder->hasDefinition(MessageBusInterface::class)) {
            $builder->register(HandlerLocator::class);

            $builder->register(HandleMessageMiddleware::class)
                ->addArgument(new Reference(HandlerLocator::class));

            $builder->register(MessageBus::class)
                ->addArgument([new Reference(HandleMessageMiddleware::class)]);

            $builder->setAlias(MessageBusInterface::class, MessageBus::class);
        }

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
