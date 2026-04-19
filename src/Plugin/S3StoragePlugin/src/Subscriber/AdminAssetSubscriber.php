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

namespace WPPack\Plugin\S3StoragePlugin\Subscriber;

use WPPack\Component\Asset\AssetManager;
use WPPack\Component\EventDispatcher\Attribute\AsEventListener;
use WPPack\Component\Nonce\NonceManager;
use WPPack\Component\Rest\RestUrlGenerator;
use WPPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;

final readonly class AdminAssetSubscriber
{
    public function __construct(
        private string $pluginUrl,
        private UploadPolicy $policy,
        private AssetManager $asset,
        private NonceManager $nonce,
        private RestUrlGenerator $restUrl,
    ) {}

    #[AsEventListener(event: 'admin_enqueue_scripts')]
    public function enqueueScripts(): void
    {
        if (!$this->asset->scriptIs('media-upload', 'enqueued') && !$this->asset->scriptIs('media-views', 'enqueued')) {
            return;
        }

        $this->asset->enqueueScript(
            'wppack-s3-upload',
            $this->pluginUrl . 'assets/js/s3-upload.js',
            ['jquery', 'media-views'],
            '1.0.0',
            true,
        );

        try {
            $config = json_encode([
                'presignedUrl' => $this->restUrl->url('wppack/v1/s3/presigned-url'),
                'registerUrl' => $this->restUrl->url('wppack/v1/s3/register-attachment'),
                'nonce' => $this->nonce->create('wp_rest'),
                'maxFileSize' => $this->policy->getMaxFileSize(),
                'allowedTypes' => $this->policy->getAllowedMimeTypes(),
            ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException) {
            return;
        }

        $this->asset->addInlineScript('wppack-s3-upload', sprintf(
            'var wppS3Upload = %s;',
            $config,
        ), 'before');
    }
}
