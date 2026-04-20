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

    #[Test]
    public function enqueueScriptsRegistersUploaderWhenMediaViewsPresent(): void
    {
        wp_register_script('media-views', 'https://example.test/media-views.js');
        wp_enqueue_script('media-views');

        $policy = new UploadPolicy(allowedMimeTypes: ['image/png', 'image/jpeg'], maxFileSize: 10 * 1024 * 1024);
        $restRegistry = new \WPPack\Component\Rest\RestRegistry($this->createMock(\WPPack\Component\HttpFoundation\Request::class));
        $subscriber = new AdminAssetSubscriber(
            pluginUrl: 'https://example.test/wp-content/plugins/wppack-s3-storage/',
            policy: $policy,
            asset: new AssetManager(),
            nonce: new NonceManager(),
            restUrl: new RestUrlGenerator($restRegistry),
        );

        $subscriber->enqueueScripts();

        self::assertTrue(wp_script_is('wppack-s3-upload', 'enqueued'));

        // Inline config must be attached before the script so JS sees wppS3Upload
        $inline = wp_scripts()->get_data('wppack-s3-upload', 'before');
        if (\is_array($inline)) {
            $inline = implode('', array_filter($inline, 'is_string'));
        }
        self::assertIsString($inline);
        self::assertStringContainsString('wppS3Upload', $inline);
        self::assertStringContainsString('presignedUrl', $inline);
        // JSON encoding leaves / as \/ so match on the escaped form too
        self::assertMatchesRegularExpression('#image\\\\?/png#', $inline);

        wp_dequeue_script('wppack-s3-upload');
        wp_dequeue_script('media-views');
        wp_deregister_script('wppack-s3-upload');
        wp_deregister_script('media-views');
    }
}
