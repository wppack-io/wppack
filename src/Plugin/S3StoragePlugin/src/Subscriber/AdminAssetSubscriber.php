<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Subscriber;

use WpPack\Component\Asset\AssetManager;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminEnqueueScriptsAction;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Nonce\NonceManager;
use WpPack\Component\Plugin\PluginPathResolver;
use WpPack\Component\Rest\RestUrlGenerator;
use WpPack\Plugin\S3StoragePlugin\PreSignedUrl\UploadPolicy;

#[AsHookSubscriber]
final readonly class AdminAssetSubscriber
{
    public function __construct(
        private PluginPathResolver $pluginPath,
        private UploadPolicy $policy,
        private AssetManager $asset,
        private NonceManager $nonce,
        private RestUrlGenerator $restUrl,
    ) {}

    #[AdminEnqueueScriptsAction]
    public function enqueueScripts(): void
    {
        if (!$this->asset->scriptIs('media-upload', 'enqueued') && !$this->asset->scriptIs('media-views', 'enqueued')) {
            return;
        }

        $this->asset->enqueueScript(
            'wppack-s3-upload',
            $this->pluginPath->getUrl() . 'assets/js/s3-upload.js',
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
