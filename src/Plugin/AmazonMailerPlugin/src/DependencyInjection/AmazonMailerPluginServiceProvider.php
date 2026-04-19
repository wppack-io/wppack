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

namespace WPPack\Plugin\AmazonMailerPlugin\DependencyInjection;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WPPack\Component\Messenger\Handler\HandlerLocator;
use WPPack\Component\Messenger\MessageBus;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Messenger\Middleware\HandleMessageMiddleware;
use WPPack\Component\Mailer\Mailer;
use WPPack\Component\Mailer\Transport\NativeTransportFactory;
use WPPack\Component\Mailer\Transport\Transport;
use WPPack\Component\Option\OptionManager;
use WPPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;
use WPPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler;
use WPPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler;
use WPPack\Plugin\AmazonMailerPlugin\Message\SesNotificationNormalizer;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsController;
use WPPack\Plugin\AmazonMailerPlugin\Admin\AmazonMailerSettingsPage;
use WPPack\Plugin\AmazonMailerPlugin\SuppressionList;

final class AmazonMailerPluginServiceProvider implements ServiceProviderInterface
{
    public function registerAdmin(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        $builder->register(AmazonMailerSettingsPage::class);
        $builder->register(AmazonMailerSettingsController::class);
    }

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
            ->setFactory([AmazonMailerConfiguration::class, 'fromEnvironmentOrOptions']);

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
        // WordPress bundles PHPMailer but it's not in Composer autoload.
        // Load WP_PHPMailer (WP 6.8+) which provides i18n support.
        if (!class_exists(\WP_PHPMailer::class, false)) {
            require_once ABSPATH . 'wp-includes/PHPMailer/PHPMailer.php';
            require_once ABSPATH . 'wp-includes/PHPMailer/Exception.php';
            require_once ABSPATH . 'wp-includes/class-wp-phpmailer.php';
        }

        return new Mailer(transport: $config->dsn);
    }
}
