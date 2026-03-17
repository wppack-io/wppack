<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\PreSignedUrl;

use AsyncAws\S3\Input\PutObjectRequest;
use AsyncAws\S3\S3Client;

final readonly class PreSignedUrlGenerator
{
    public function __construct(
        private S3Client $s3Client,
        private string $bucket,
        private string $prefix,
    ) {}

    public function generate(
        string $filename,
        string $contentType,
        int $contentLength,
        int $expiresIn = 3600,
    ): PreSignedUrlResult {
        $key = $this->buildKey($filename);

        $input = new PutObjectRequest([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
            'ContentLength' => $contentLength,
        ]);

        $expiration = new \DateTimeImmutable(sprintf('+%d seconds', $expiresIn));
        $url = $this->s3Client->presign($input, $expiration);

        return new PreSignedUrlResult(
            url: $url,
            key: $key,
            expiresIn: $expiresIn,
        );
    }

    private function buildKey(string $filename): string
    {
        $datePath = gmdate('Y/m');
        $uniqueId = bin2hex(random_bytes(8));
        $sanitizedFilename = $this->sanitizeFilename($filename);

        return sprintf('%s/%s/%s-%s', rtrim($this->prefix, '/'), $datePath, $uniqueId, $sanitizedFilename);
    }

    private function sanitizeFilename(string $filename): string
    {
        if (function_exists('sanitize_file_name')) {
            return sanitize_file_name($filename);
        }

        // Fallback: basic sanitization
        $filename = preg_replace('/[^\w\-.]/', '-', $filename) ?? $filename;

        return trim($filename, '-');
    }
}
