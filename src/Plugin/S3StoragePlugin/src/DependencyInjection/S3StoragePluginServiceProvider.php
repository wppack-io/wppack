<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\DependencyInjection;

use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\AttachmentSubscriber;
use WpPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
use WpPack\Component\Media\Storage\UrlResolver;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WpPack\Component\Storage\StreamWrapper\StorageStreamWrapper;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Handler\GenerateThumbnailsHandler;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;
use WpPack\Plugin\S3StoragePlugin\Message\S3EventNormalizer;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlController;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlGenerator;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;

final class S3StoragePluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Configuration
        $builder->register(S3StorageConfiguration::class)
            ->setFactory([S3StorageConfiguration::class, 'fromEnvironment']);

        $builder->register(StorageConfiguration::class)
            ->setFactory([new Reference(S3StorageConfiguration::class), 'toStorageConfiguration']);

        // AWS S3 Client
        $builder->register(S3Client::class)
            ->setFactory([self::class, 'createS3Client'])
            ->addArgument(new Reference(S3StorageConfiguration::class));

        // Storage Adapter
        $builder->register(S3StorageAdapter::class)
            ->setFactory([self::class, 'createS3StorageAdapter'])
            ->addArgument(new Reference(S3Client::class))
            ->addArgument(new Reference(S3StorageConfiguration::class));

        $builder->setAlias(StorageAdapterInterface::class, S3StorageAdapter::class);

        // Stream Wrapper Registration
        $builder->register(StorageStreamWrapper::class . '.registrar')
            ->setClass(StorageStreamWrapper::class)
            ->setFactory([self::class, 'registerStreamWrapper'])
            ->addArgument(new Reference(S3StorageAdapter::class));

        // URL Resolver
        $builder->register(UrlResolver::class)
            ->setFactory([self::class, 'createUrlResolver'])
            ->addArgument(new Reference(S3StorageAdapter::class))
            ->addArgument(new Reference(S3StorageConfiguration::class));

        // Media Storage Subscribers
        $builder->register(UploadDirSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(UrlResolver::class))
            ->addTag('hook.subscriber');

        $builder->register(AttachmentSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(UrlResolver::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addTag('hook.subscriber');

        // Pre-Signed URL services
        $builder->register(UploadPolicy::class);

        $builder->register(PreSignedUrlGenerator::class)
            ->setFactory([self::class, 'createPreSignedUrlGenerator'])
            ->addArgument(new Reference(S3Client::class))
            ->addArgument(new Reference(S3StorageConfiguration::class));

        $builder->register(PreSignedUrlController::class)
            ->addArgument(new Reference(PreSignedUrlGenerator::class))
            ->addArgument(new Reference(UploadPolicy::class))
            ->addTag('rest.controller');

        // Message handlers
        $builder->register(S3EventNormalizer::class);

        $builder->register(S3ObjectCreatedHandler::class)
            ->setFactory([self::class, 'createS3ObjectCreatedHandler'])
            ->addArgument(new Reference(MessageBusInterface::class))
            ->addArgument(new Reference(S3StorageConfiguration::class))
            ->addTag('messenger.message_handler');

        $builder->register(GenerateThumbnailsHandler::class)
            ->addTag('messenger.message_handler');
    }

    public static function createS3Client(S3StorageConfiguration $config): S3Client
    {
        return new S3Client(['region' => $config->region]);
    }

    public static function createS3StorageAdapter(
        S3Client $s3Client,
        S3StorageConfiguration $config,
    ): S3StorageAdapter {
        return new S3StorageAdapter(
            s3Client: $s3Client,
            bucket: $config->bucket,
            prefix: $config->prefix,
            publicUrl: $config->cdnUrl,
        );
    }

    public static function registerStreamWrapper(S3StorageAdapter $adapter): void
    {
        StorageStreamWrapper::register('s3', $adapter);
    }

    public static function createUrlResolver(
        S3StorageAdapter $adapter,
        S3StorageConfiguration $config,
    ): UrlResolver {
        return new UrlResolver($adapter, $config->cdnUrl);
    }

    public static function createPreSignedUrlGenerator(
        S3Client $s3Client,
        S3StorageConfiguration $config,
    ): PreSignedUrlGenerator {
        return new PreSignedUrlGenerator(
            s3Client: $s3Client,
            bucket: $config->bucket,
            prefix: $config->prefix,
        );
    }

    public static function createS3ObjectCreatedHandler(
        MessageBusInterface $bus,
        S3StorageConfiguration $config,
        ?LoggerInterface $logger = null,
    ): S3ObjectCreatedHandler {
        return new S3ObjectCreatedHandler(
            bus: $bus,
            prefix: $config->prefix,
            logger: $logger,
        );
    }
}
