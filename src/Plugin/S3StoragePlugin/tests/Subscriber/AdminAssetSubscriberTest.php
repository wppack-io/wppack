<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Asset\AssetManager;
use WpPack\Component\Nonce\NonceManager;
use WpPack\Component\Plugin\PluginPathResolver;
use WpPack\Component\Rest\RestUrlGenerator;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;
use WpPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber;

#[CoversClass(AdminAssetSubscriber::class)]
final class AdminAssetSubscriberTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $policy = new UploadPolicy(allowedMimeTypes: []);
        $subscriber = new AdminAssetSubscriber(
            pluginPath: new PluginPathResolver(__DIR__ . '/../../s3-storage-plugin.php'),
            policy: $policy,
            asset: new AssetManager(),
            nonce: new NonceManager(),
            restUrl: new RestUrlGenerator(),
        );

        self::assertInstanceOf(AdminAssetSubscriber::class, $subscriber);
    }

    #[Test]
    public function enqueueScriptsSkipsWhenMediaScriptsNotLoaded(): void
    {
        $policy = new UploadPolicy(allowedMimeTypes: []);
        $subscriber = new AdminAssetSubscriber(
            pluginPath: new PluginPathResolver(__DIR__ . '/../../s3-storage-plugin.php'),
            policy: $policy,
            asset: new AssetManager(),
            nonce: new NonceManager(),
            restUrl: new RestUrlGenerator(),
        );

        // When neither media-upload nor media-views is enqueued, should silently return
        $subscriber->enqueueScripts();

        self::assertFalse(wp_script_is('wppack-s3-upload', 'enqueued'));
    }
}
