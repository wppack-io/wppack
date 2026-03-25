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

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

use WpPack\Component\Storage\Adapter\StorageAdapterInterface;

final readonly class PreSignedUrlGenerator
{
    public function __construct(
        private StorageAdapterInterface $storage,
    ) {}

    public function generate(
        string $filename,
        string $contentType,
        int $contentLength,
        int $expiresIn = 3600,
    ): PreSignedUrlResult {
        $path = $this->buildPath($filename);

        $expiration = new \DateTimeImmutable(sprintf('+%d seconds', $expiresIn));
        $url = $this->storage->temporaryUploadUrl($path, $expiration, [
            'Content-Type' => $contentType,
            'Content-Length' => $contentLength,
        ]);

        return new PreSignedUrlResult(
            url: $url,
            key: $path,
            expiresIn: $expiresIn,
        );
    }

    private function buildPath(string $filename): string
    {
        $filename = basename($filename);
        $datePath = gmdate('Y/m');
        $uniqueId = bin2hex(random_bytes(8));
        $sanitizedFilename = $this->sanitizeFilename($filename);

        return sprintf('%s/%s-%s', $datePath, $uniqueId, $sanitizedFilename);
    }

    private function sanitizeFilename(string $filename): string
    {
        return sanitize_file_name($filename);
    }
}
