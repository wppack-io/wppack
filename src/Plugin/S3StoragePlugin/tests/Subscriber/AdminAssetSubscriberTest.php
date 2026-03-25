<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Asset\AssetManager;
use WpPack\Component\Nonce\NonceManager;
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
        $restRegistry = new \WpPack\Component\Rest\RestRegistry($this->createMock(\WpPack\Component\HttpFoundation\Request::class));
        $subscriber = new AdminAssetSubscriber(
            pluginUrl: plugin_dir_url(__DIR__ . '/../../wppack-s3-storage.php'),
            policy: $policy,
            asset: new AssetManager(),
            nonce: new NonceManager(),
            restUrl: new RestUrlGenerator($restRegistry),
        );

        self::assertInstanceOf(AdminAssetSubscriber::class, $subscriber);
    }

    #[Test]
    public function enqueueScriptsSkipsWhenMediaScriptsNotLoaded(): void
    {
        $policy = new UploadPolicy(allowedMimeTypes: []);
        $restRegistry = new \WpPack\Component\Rest\RestRegistry($this->createMock(\WpPack\Component\HttpFoundation\Request::class));
        $subscriber = new AdminAssetSubscriber(
            pluginUrl: plugin_dir_url(__DIR__ . '/../../wppack-s3-storage.php'),
            policy: $policy,
            asset: new AssetManager(),
            nonce: new NonceManager(),
            restUrl: new RestUrlGenerator($restRegistry),
        );

        // When neither media-upload nor media-views is enqueued, should silently return
        $subscriber->enqueueScripts();

        self::assertFalse(wp_script_is('wppack-s3-upload', 'enqueued'));
    }
}
