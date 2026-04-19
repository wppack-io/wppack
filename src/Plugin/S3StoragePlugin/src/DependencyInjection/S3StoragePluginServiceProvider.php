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

namespace WPPack\Plugin\S3StoragePlugin\DependencyInjection;

use AsyncAws\S3\S3Client;
use Psr\Log\LoggerInterface;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Asset\AssetManager;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Media\AttachmentManager;
use WPPack\Component\Media\AttachmentManagerInterface;
use WPPack\Component\PostType\PostRepository;
use WPPack\Component\PostType\PostRepositoryInterface;
use WPPack\Component\Media\Storage\PrivateAttachmentChecker;
use WPPack\Component\Media\Storage\SignedUrlCache;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Media\Storage\Subscriber\AttachmentSubscriber;
use WPPack\Component\Media\Storage\Subscriber\ImageEditorSubscriber;
use WPPack\Component\Media\Storage\Subscriber\PrivacyExportSubscriber;
use WPPack\Component\Media\Storage\Subscriber\PrivateAttachmentSubscriber;
use WPPack\Component\Media\Storage\Subscriber\SideloadSubscriber;
use WPPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
use WPPack\Component\Media\Storage\ImageEditor\StorageImageEditor;
use WPPack\Component\Media\Storage\UrlResolver;
use WPPack\Component\Messenger\Handler\HandlerLocator;
use WPPack\Component\Messenger\MessageBus;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Messenger\Middleware\HandleMessageMiddleware;
use WPPack\Component\Nonce\NonceManager;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Rest\RestUrlGenerator;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Site\BlogSwitcher;
use WPPack\Component\Site\BlogSwitcherInterface;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;
use WPPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WPPack\Component\Storage\StreamWrapper\StorageStreamWrapper;
use WPPack\Component\Transient\TransientManager;
use WPPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsController;
use WPPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsPage;
use WPPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WPPack\Plugin\S3StoragePlugin\Attachment\RegisterAttachmentController;
use WPPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WPPack\Plugin\S3StoragePlugin\Handler\GenerateThumbnailsHandler;
use WPPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;
use WPPack\Plugin\S3StoragePlugin\Handler\S3ObjectRemovedHandler;
use WPPack\Plugin\S3StoragePlugin\Message\S3EventNormalizer;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlController;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlGenerator;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;
use WPPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber;
use WPPack\Plugin\S3StoragePlugin\Subscriber\PrivateAttachmentAclSubscriber;

final class S3StoragePluginServiceProvider implements ServiceProviderInterface
{
    public function __construct(
        private readonly string $pluginFile,
    ) {}

    public function registerAdmin(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AdminPageRegistry::class)) {
            $builder->register(AdminPageRegistry::class);
        }

        if (!$builder->hasDefinition(RestRegistry::class)) {
            $builder->register(RestRegistry::class)
                ->addArgument(new Reference(Request::class));
        }

        $builder->register(S3StorageSettingsPage::class);
        $builder->register(S3StorageSettingsController::class);
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

        // Site component services
        $builder->register(BlogContextInterface::class, BlogContext::class);
        $builder->register(BlogSwitcherInterface::class, BlogSwitcher::class)
            ->addArgument(new Reference(BlogContextInterface::class));

        // Configuration
        $builder->register(S3StorageConfiguration::class)
            ->setFactory([S3StorageConfiguration::class, 'fromEnvironmentOrOptions']);

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

        $builder->register(ImageEditorSubscriber::class)
            ->addArgument(StorageImageEditor::class)
            ->addTag('hook.subscriber');

        $builder->register(SideloadSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addTag('hook.subscriber');

        $builder->register(PrivacyExportSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addTag('hook.subscriber');

        // Private Attachment support
        $builder->register(TransientManager::class);

        $builder->register(PrivateAttachmentChecker::class);

        $builder->register(SignedUrlCache::class)
            ->addArgument(new Reference(TransientManager::class));

        $builder->register(PrivateAttachmentSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addArgument(new Reference(PrivateAttachmentChecker::class))
            ->addArgument(new Reference(SignedUrlCache::class))
            ->addTag('hook.subscriber');

        $builder->register(PrivateAttachmentAclSubscriber::class)
            ->addArgument(new Reference(StorageConfiguration::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addArgument(new Reference(PrivateAttachmentChecker::class))
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

        $builder->setAlias(AttachmentManagerInterface::class, AttachmentManager::class);

        // Attachment registration
        $builder->register(AttachmentRegistrar::class)
            ->setFactory([self::class, 'createAttachmentRegistrar'])
            ->addArgument(new Reference(MessageBusInterface::class))
            ->addArgument(new Reference(S3StorageConfiguration::class))
            ->addArgument(new Reference(BlogSwitcherInterface::class))
            ->addArgument(new Reference(AttachmentManagerInterface::class));

        $builder->register(RegisterAttachmentController::class)
            ->addArgument(new Reference(AttachmentRegistrar::class))
            ->addArgument(new Reference(StorageAdapterInterface::class))
            ->addArgument(new Reference(AttachmentManagerInterface::class))
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
            ->addArgument(new Reference(AttachmentManagerInterface::class))
            ->addTag('messenger.message_handler');
    }

    public static function createS3Client(S3StorageConfiguration $config): S3Client
    {
        $options = ['region' => $config->region];

        if ($config->accessKeyId !== null && $config->secretAccessKey !== null) {
            $options['accessKeyId'] = $config->accessKeyId;
            $options['accessKeySecret'] = $config->secretAccessKey;
        }

        return new S3Client($options);
    }

    public static function createS3StorageAdapter(
        S3Client $s3Client,
        S3StorageConfiguration $config,
    ): S3StorageAdapter {
        return new S3StorageAdapter(
            s3Client: $s3Client,
            bucket: $config->bucket,
            prefix: $config->uploadsPath,
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
        AttachmentManagerInterface $attachment,
        ?LoggerInterface $logger = null,
    ): AttachmentRegistrar {
        return new AttachmentRegistrar(
            bus: $bus,
            prefix: $config->uploadsPath,
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
