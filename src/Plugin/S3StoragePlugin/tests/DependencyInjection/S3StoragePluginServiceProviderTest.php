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

namespace WPPack\Plugin\S3StoragePlugin\Tests\DependencyInjection;

use AsyncAws\S3\S3Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Asset\AssetManager;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Media\AttachmentManager;
use WPPack\Component\Media\AttachmentManagerInterface;
use WPPack\Component\PostType\PostRepository;
use WPPack\Component\PostType\PostRepositoryInterface;
use WPPack\Component\Media\Storage\StorageConfiguration;
use WPPack\Component\Media\Storage\Subscriber\AttachmentSubscriber;
use WPPack\Component\Media\Storage\Subscriber\UploadDirSubscriber;
use WPPack\Component\Media\Storage\UrlResolver;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Nonce\NonceManager;
use WPPack\Component\Rest\RestUrlGenerator;
use WPPack\Component\Storage\Adapter\StorageAdapterInterface;
use WPPack\Component\Storage\Bridge\S3\S3StorageAdapter;
use WPPack\Component\Storage\StreamWrapper\StorageStreamWrapper;
use WPPack\Plugin\S3StoragePlugin\Attachment\AttachmentRegistrar;
use WPPack\Plugin\S3StoragePlugin\Attachment\RegisterAttachmentController;
use WPPack\Plugin\S3StoragePlugin\Configuration\S3StorageConfiguration;
use WPPack\Plugin\S3StoragePlugin\DependencyInjection\S3StoragePluginServiceProvider;
use WPPack\Plugin\S3StoragePlugin\Handler\GenerateThumbnailsHandler;
use WPPack\Plugin\S3StoragePlugin\Handler\S3ObjectCreatedHandler;
use WPPack\Plugin\S3StoragePlugin\Handler\S3ObjectRemovedHandler;
use WPPack\Plugin\S3StoragePlugin\Message\S3EventNormalizer;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlController;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\PreSignedUrlGenerator;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;
use WPPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsController;
use WPPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsPage;
use WPPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber;

#[CoversClass(S3StoragePluginServiceProvider::class)]
final class S3StoragePluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private S3StoragePluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new S3StoragePluginServiceProvider('/path/to/plugin.php');
    }

    private function createConfig(
        string $bucket = 'test-bucket',
        string $region = 'us-east-1',
        string $uploadsPath = 'wp-content/uploads',
        ?string $cdnUrl = null,
        ?string $accessKeyId = null,
        ?string $secretAccessKey = null,
    ): S3StorageConfiguration {
        return new S3StorageConfiguration(
            dsn: 's3://' . ($accessKeyId !== null && $secretAccessKey !== null ? $accessKeyId . ':' . $secretAccessKey . '@' : '') . $bucket . '?region=' . $region,
            bucket: $bucket,
            region: $region,
            uploadsPath: $uploadsPath,
            cdnUrl: $cdnUrl,
            accessKeyId: $accessKeyId,
            secretAccessKey: $secretAccessKey,
        );
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        self::assertInstanceOf(ServiceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function registerAdminRegistersSettingsPageAndController(): void
    {
        $this->provider->registerAdmin($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3StorageSettingsPage::class));
        self::assertTrue($this->builder->hasDefinition(S3StorageSettingsController::class));
    }

    #[Test]
    public function registersS3StorageConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3StorageConfiguration::class));

        $definition = $this->builder->findDefinition(S3StorageConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StorageConfiguration::class, $factory[0]);
        self::assertSame('fromEnvironmentOrOptions', $factory[1]);
    }

    #[Test]
    public function registersStorageConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(StorageConfiguration::class));

        $definition = $this->builder->findDefinition(StorageConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(S3StorageConfiguration::class, (string) $factory[0]);
        self::assertSame('toStorageConfiguration', $factory[1]);
    }

    #[Test]
    public function registersS3Client(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3Client::class));

        $definition = $this->builder->findDefinition(S3Client::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StoragePluginServiceProvider::class, $factory[0]);
        self::assertSame('createS3Client', $factory[1]);
    }

    #[Test]
    public function registersS3StorageAdapter(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3StorageAdapter::class));

        $definition = $this->builder->findDefinition(S3StorageAdapter::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StoragePluginServiceProvider::class, $factory[0]);
        self::assertSame('createS3StorageAdapter', $factory[1]);
    }

    #[Test]
    public function registersStorageAdapterInterfaceAlias(): void
    {
        $this->provider->register($this->builder);

        // The alias should resolve
        self::assertTrue($this->builder->hasDefinition(S3StorageAdapter::class));
    }

    #[Test]
    public function registersStreamWrapperRegistrar(): void
    {
        $this->provider->register($this->builder);

        $registrarId = StorageStreamWrapper::class . '.registrar';
        self::assertTrue($this->builder->hasDefinition($registrarId));

        $definition = $this->builder->findDefinition($registrarId);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StoragePluginServiceProvider::class, $factory[0]);
        self::assertSame('registerStreamWrapper', $factory[1]);
    }

    #[Test]
    public function registersUrlResolver(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(UrlResolver::class));

        $definition = $this->builder->findDefinition(UrlResolver::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StoragePluginServiceProvider::class, $factory[0]);
        self::assertSame('createUrlResolver', $factory[1]);
    }

    #[Test]
    public function registersUploadDirSubscriber(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(UploadDirSubscriber::class));

        $definition = $this->builder->findDefinition(UploadDirSubscriber::class);
        self::assertTrue($definition->hasTag('hook.subscriber'));
    }

    #[Test]
    public function registersAttachmentSubscriber(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AttachmentSubscriber::class));

        $definition = $this->builder->findDefinition(AttachmentSubscriber::class);
        self::assertTrue($definition->hasTag('hook.subscriber'));
    }

    #[Test]
    public function registersUploadPolicy(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(UploadPolicy::class));
    }

    #[Test]
    public function registersPreSignedUrlGenerator(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(PreSignedUrlGenerator::class));

        $definition = $this->builder->findDefinition(PreSignedUrlGenerator::class);
        self::assertNull($definition->getFactory());

        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(StorageAdapterInterface::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersPreSignedUrlController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(PreSignedUrlController::class));

        $definition = $this->builder->findDefinition(PreSignedUrlController::class);
        self::assertTrue($definition->hasTag('rest.controller'));
    }

    #[Test]
    public function registersAttachmentRegistrar(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AttachmentRegistrar::class));

        $definition = $this->builder->findDefinition(AttachmentRegistrar::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StoragePluginServiceProvider::class, $factory[0]);
        self::assertSame('createAttachmentRegistrar', $factory[1]);
    }

    #[Test]
    public function registersRegisterAttachmentController(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(RegisterAttachmentController::class));

        $definition = $this->builder->findDefinition(RegisterAttachmentController::class);
        self::assertTrue($definition->hasTag('rest.controller'));
    }

    #[Test]
    public function registersAdminAssetSubscriber(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AdminAssetSubscriber::class));

        $definition = $this->builder->findDefinition(AdminAssetSubscriber::class);
        self::assertTrue($definition->hasTag('hook.subscriber'));

        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(S3StoragePluginServiceProvider::class, $factory[0]);
        self::assertSame('createAdminAssetSubscriber', $factory[1]);
    }

    #[Test]
    public function registersS3EventNormalizer(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3EventNormalizer::class));
    }

    #[Test]
    public function registersS3ObjectCreatedHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3ObjectCreatedHandler::class));

        $definition = $this->builder->findDefinition(S3ObjectCreatedHandler::class);
        self::assertTrue($definition->hasTag('messenger.message_handler'));

        $arguments = $definition->getArguments();
        self::assertCount(2, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(AttachmentRegistrar::class, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(S3StorageConfiguration::class, (string) $arguments[1]);
    }

    #[Test]
    public function registersS3ObjectRemovedHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(S3ObjectRemovedHandler::class));

        $definition = $this->builder->findDefinition(S3ObjectRemovedHandler::class);
        self::assertTrue($definition->hasTag('messenger.message_handler'));

        $arguments = $definition->getArguments();
        self::assertCount(2, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(AttachmentRegistrar::class, (string) $arguments[0]);
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame(S3StorageConfiguration::class, (string) $arguments[1]);
    }

    #[Test]
    public function registersGenerateThumbnailsHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(GenerateThumbnailsHandler::class));

        $definition = $this->builder->findDefinition(GenerateThumbnailsHandler::class);
        self::assertTrue($definition->hasTag('messenger.message_handler'));
    }

    #[Test]
    public function createS3ClientReturnsS3ClientInstance(): void
    {
        $config = $this->createConfig();

        $client = S3StoragePluginServiceProvider::createS3Client($config);

        self::assertInstanceOf(S3Client::class, $client);
    }

    #[Test]
    public function createS3ClientWithCredentials(): void
    {
        $config = $this->createConfig(
            accessKeyId: 'AKIAEXAMPLE',
            secretAccessKey: 'secret-key',
        );

        $client = S3StoragePluginServiceProvider::createS3Client($config);

        self::assertInstanceOf(S3Client::class, $client);
    }

    #[Test]
    public function createS3StorageAdapterReturnsAdapterInstance(): void
    {
        $s3Client = new S3Client(['region' => 'us-east-1']);
        $config = $this->createConfig(
            uploadsPath: 'wp-content/uploads',
            cdnUrl: 'https://cdn.example.com',
        );

        $adapter = S3StoragePluginServiceProvider::createS3StorageAdapter($s3Client, $config);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function createS3StorageAdapterWithoutCdnUrl(): void
    {
        $s3Client = new S3Client(['region' => 'us-east-1']);
        $config = $this->createConfig();

        $adapter = S3StoragePluginServiceProvider::createS3StorageAdapter($s3Client, $config);

        self::assertInstanceOf(S3StorageAdapter::class, $adapter);
    }

    #[Test]
    public function registerStreamWrapperRegistersS3Protocol(): void
    {
        $s3Client = new S3Client(['region' => 'us-east-1']);
        $config = $this->createConfig();
        $adapter = S3StoragePluginServiceProvider::createS3StorageAdapter($s3Client, $config);

        // Unregister if already registered from a previous test
        if (\in_array('s3', stream_get_wrappers(), true)) {
            stream_wrapper_unregister('s3');
        }

        S3StoragePluginServiceProvider::registerStreamWrapper($adapter);

        self::assertContains('s3', stream_get_wrappers());

        // Clean up
        StorageStreamWrapper::unregister('s3');
    }

    #[Test]
    public function createUrlResolverReturnsUrlResolverInstance(): void
    {
        $s3Client = new S3Client(['region' => 'us-east-1']);
        $config = $this->createConfig(cdnUrl: 'https://cdn.example.com');
        $adapter = S3StoragePluginServiceProvider::createS3StorageAdapter($s3Client, $config);

        $resolver = S3StoragePluginServiceProvider::createUrlResolver($adapter, $config);

        self::assertInstanceOf(UrlResolver::class, $resolver);
    }

    #[Test]
    public function createUrlResolverWithoutCdnUrl(): void
    {
        $s3Client = new S3Client(['region' => 'us-east-1']);
        $config = $this->createConfig();
        $adapter = S3StoragePluginServiceProvider::createS3StorageAdapter($s3Client, $config);

        $resolver = S3StoragePluginServiceProvider::createUrlResolver($adapter, $config);

        self::assertInstanceOf(UrlResolver::class, $resolver);
    }

    #[Test]
    public function createAttachmentRegistrarReturnsRegistrarInstance(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $config = $this->createConfig(uploadsPath: 'wp-content/uploads');

        $blogSwitcher = $this->createMock(\WPPack\Component\Site\BlogSwitcherInterface::class);
        $attachment = new AttachmentManager(new PostRepository());
        $registrar = S3StoragePluginServiceProvider::createAttachmentRegistrar($bus, $config, $blogSwitcher, $attachment);

        self::assertInstanceOf(AttachmentRegistrar::class, $registrar);
    }

    #[Test]
    public function createAttachmentRegistrarWithLogger(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $config = $this->createConfig(uploadsPath: 'wp-content/uploads');

        $blogSwitcher = $this->createMock(\WPPack\Component\Site\BlogSwitcherInterface::class);
        $attachment = new AttachmentManager(new PostRepository());
        $registrar = S3StoragePluginServiceProvider::createAttachmentRegistrar($bus, $config, $blogSwitcher, $attachment, $logger);

        self::assertInstanceOf(AttachmentRegistrar::class, $registrar);
    }

    #[Test]
    public function createAdminAssetSubscriberReturnsSubscriberInstance(): void
    {
        $pluginUrl = 'https://example.com/wp-content/plugins/s3-storage-plugin/';
        $policy = new UploadPolicy(allowedMimeTypes: []);
        $asset = new AssetManager();
        $nonce = new NonceManager();
        $restUrl = new RestUrlGenerator(new \WPPack\Component\Rest\RestRegistry($this->createMock(\WPPack\Component\HttpFoundation\Request::class)));

        $subscriber = S3StoragePluginServiceProvider::createAdminAssetSubscriber($pluginUrl, $policy, $asset, $nonce, $restUrl);

        self::assertInstanceOf(AdminAssetSubscriber::class, $subscriber);
    }

    #[Test]
    public function registersPostRepositoryInterface(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(PostRepositoryInterface::class));
    }

    #[Test]
    public function registersAttachmentManager(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AttachmentManager::class));

        $definition = $this->builder->findDefinition(AttachmentManager::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(PostRepositoryInterface::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersNonceManager(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(NonceManager::class));
    }

    #[Test]
    public function registersRestUrlGenerator(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(RestUrlGenerator::class));
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $result = $this->builder->addServiceProvider($this->provider);

        self::assertSame($this->builder, $result);
        self::assertTrue($this->builder->hasDefinition(S3StorageConfiguration::class));
        self::assertTrue($this->builder->hasDefinition(S3Client::class));
        self::assertTrue($this->builder->hasDefinition(S3StorageAdapter::class));
        self::assertTrue($this->builder->hasDefinition(UrlResolver::class));
        self::assertTrue($this->builder->hasDefinition(UploadPolicy::class));
        self::assertTrue($this->builder->hasDefinition(PreSignedUrlGenerator::class));
        self::assertTrue($this->builder->hasDefinition(PreSignedUrlController::class));
        self::assertTrue($this->builder->hasDefinition(S3EventNormalizer::class));
        self::assertTrue($this->builder->hasDefinition(S3ObjectCreatedHandler::class));
        self::assertTrue($this->builder->hasDefinition(S3ObjectRemovedHandler::class));
        self::assertTrue($this->builder->hasDefinition(GenerateThumbnailsHandler::class));
        self::assertTrue($this->builder->hasDefinition(AttachmentRegistrar::class));
        self::assertTrue($this->builder->hasDefinition(RegisterAttachmentController::class));
        self::assertTrue($this->builder->hasDefinition(AdminAssetSubscriber::class));
        self::assertTrue($this->builder->hasDefinition(PostRepositoryInterface::class));
        self::assertTrue($this->builder->hasDefinition(AttachmentManager::class));
        self::assertTrue($this->builder->hasDefinition(NonceManager::class));
        self::assertTrue($this->builder->hasDefinition(RestUrlGenerator::class));
    }
}
