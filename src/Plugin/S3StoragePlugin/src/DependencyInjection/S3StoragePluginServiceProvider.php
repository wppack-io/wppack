<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\DependencyInjection;

use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use WpPack\Component\Asset\AssetManager;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Media\AttachmentManager;
use WpPack\Component\PostType\PostRepository;
use WpPack\Component\PostType\PostRepositoryInterface;
use WpPack\Component\Media\Storage\StorageConfiguration;
use WpPack\Component\Media\Storage\Subscriber\AttachmentSubscriber;
use WpPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
use WpPack\Component\Media\Storage\UrlResolver;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Nonce\NonceManager;
use WpPack\Component\Rest\RestUrlGenerator;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Component\Site\BlogSwitcher;
use WpPack\Component\Site\BlogSwitcherInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WpPack\Component\Storage\StreamWrapper\StorageStreamWrapper;
use WpPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WpPack\Plugin\S3StoragePlugin\Attachment\RegisterAttachmentController;
use WpPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WpPack\Plugin\S3StoragePlugin\Handler\GenerateThumbnailsHandler;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;
use WpPack\Plugin\S3StoragePlugin\Handler\S3ObjectRemovedHandler;
use WpPack\Plugin\S3StoragePlugin\Message\S3EventNormalizer;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlController;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlGenerator;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;
use WpPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber;

final class S3StoragePluginServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly string $pluginFile,
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        // Site component services
        $builder->register(BlogContextInterface::class, BlogContext::class);
        $builder->register(BlogSwitcherInterface::class, BlogSwitcher::class)
            ->addArgument(new Reference(BlogContextInterface::class));

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
            ->addArgument(new Reference(BlogContextInterface::class))
            ->addTag('hook.subscriber');

        $builder->register(AttachmentSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(UrlResolver::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addTag('hook.subscriber');

        // Pre-Signed URL services
        $builder->register(UploadPolicy::class);

        $builder->register(PreSignedUrlGenerator::class)
            ->addArgument(new Reference(StorageAdapterInterface::class));

        $builder->register(PreSignedUrlController::class)
            ->addArgument(new Reference(PreSignedUrlGenerator::class))
            ->addArgument(new Reference(UploadPolicy::class))
            ->addTag('rest.controller');

        // PostType repository
        $builder->register(PostRepositoryInterface::class, PostRepository::class);

        // Attachment manager
        $builder->register(AttachmentManager::class)
            ->addArgument(new Reference(PostRepositoryInterface::class));

        // Attachment registration
        $builder->register(AttachmentRegistrar::class)
            ->setFactory([self::class, 'createAttachmentRegistrar'])
            ->addArgument(new Reference(MessageBusInterface::class))
            ->addArgument(new Reference(S3StorageConfiguration::class))
            ->addArgument(new Reference(BlogSwitcherInterface::class))
            ->addArgument(new Reference(AttachmentManager::class));

        $builder->register(RegisterAttachmentController::class)
            ->addArgument(new Reference(AttachmentRegistrar::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addArgument(new Reference(AttachmentManager::class))
            ->addTag('rest.controller');

        // Asset Manager
        $builder->register(AssetManager::class);

        // Nonce Manager
        $builder->register(NonceManager::class);

        // REST URL Generator
        $builder->register(RestUrlGenerator::class);

        // Admin assets
        $pluginUrl = plugin_dir_url($this->pluginFile);
        $builder->register(AdminAssetSubscriber::class)
            ->setFactory([self::class, 'createAdminAssetSubscriber'])
            ->addArgument($pluginUrl)
            ->addArgument(new Reference(UploadPolicy::class))
            ->addArgument(new Reference(AssetManager::class))
            ->addArgument(new Reference(NonceManager::class))
            ->addArgument(new Reference(RestUrlGenerator::class))
            ->addTag('hook.subscriber');

        // Message handlers
        $builder->register(S3EventNormalizer::class);

        $builder->register(S3ObjectCreatedHandler::class)
            ->addArgument(new Reference(AttachmentRegistrar::class))
            ->addArgument(new Reference(S3StorageConfiguration::class))
            ->addTag('messenger.message_handler');

        $builder->register(S3ObjectRemovedHandler::class)
            ->addArgument(new Reference(AttachmentRegistrar::class))
            ->addArgument(new Reference(S3StorageConfiguration::class))
            ->addTag('messenger.message_handler');

        $builder->register(GenerateThumbnailsHandler::class)
            ->addArgument(new Reference(BlogSwitcherInterface::class))
            ->addArgument(new Reference(AttachmentManager::class))
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

    public static function createAttachmentRegistrar(
        MessageBusInterface $bus,
        S3StorageConfiguration $config,
        BlogSwitcherInterface $blogSwitcher,
        AttachmentManager $attachment,
        ?LoggerInterface $logger = null,
    ): AttachmentRegistrar {
        return new AttachmentRegistrar(
            bus: $bus,
            prefix: $config->prefix,
            blogSwitcher: $blogSwitcher,
            attachment: $attachment,
            logger: $logger,
        );
    }

    public static function createAdminAssetSubscriber(
        string $pluginUrl,
        UploadPolicy $policy,
        AssetManager $asset,
        NonceManager $nonce,
        RestUrlGenerator $restUrl,
    ): AdminAssetSubscriber {
        return new AdminAssetSubscriber(
            pluginUrl: $pluginUrl,
            policy: $policy,
            asset: $asset,
            nonce: $nonce,
            restUrl: $restUrl,
        );
    }
}
