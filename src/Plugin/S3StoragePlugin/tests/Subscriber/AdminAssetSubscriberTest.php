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

namespace WPPack\Plugin\S3StoragePlugin\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Asset\AssetManager;
use WPPack\Component\Nonce\NonceManager;
use WPPack\Component\Rest\RestUrlGenerator;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;
use WPPack\Plugin\S3StoragePlugin\Subscriber\AdminAssetSubscriber;

#[CoversClass(AdminAssetSubscriber::class)]
final class AdminAssetSubscriberTest extends TestCase
{
    #[Test]
    public function canBeInstantiated(): void
    {
        $policy = new UploadPolicy(allowedMimeTypes: []);
        $restRegistry = new \WPPack\Component\Rest\RestRegistry($this->createMock(\WPPack\Component\HttpFoundation\Request::class));
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
        $restRegistry = new \WPPack\Component\Rest\RestRegistry($this->createMock(\WPPack\Component\HttpFoundation\Request::class));
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
