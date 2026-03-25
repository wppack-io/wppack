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

namespace WpPack\Component\Storage\Bridge\S3;

use AsyncAws\S3\S3Client;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Adapter\StorageAdapterFactoryInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

final class S3StorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface
    {
        [$bucket, $region] = $this->parseHost($dsn, $options);

        if ($bucket === null) {
            throw new InvalidArgumentException('Cannot determine bucket name from S3 storage DSN. Supported formats: "s3://{bucket}.s3.{region}.amazonaws.com/{prefix}" or "s3://{bucket}".');
        }

        $prefix = $this->parsePrefix($dsn, $options);

        $publicUrl = $dsn->getOption('public_url') ?? $options['public_url'] ?? null;
        $endpoint = $dsn->getOption('endpoint') ?? $options['endpoint'] ?? null;

        $clientConfig = [];

        if ($region !== null) {
            $clientConfig['region'] = $region;
        }

        if ($endpoint !== null) {
            $clientConfig['endpoint'] = $endpoint;
        }

        $accessKey = $dsn->getUser() ?? $options['access_key'] ?? null;
        $secretKey = $dsn->getPassword() ?? $options['secret_key'] ?? null;

        if ($accessKey !== null && $secretKey !== null) {
            $clientConfig['accessKeyId'] = $accessKey;
            $clientConfig['accessKeySecret'] = $secretKey;
        }

        $s3Client = $options['s3_client'] ?? new S3Client($clientConfig);

        return new S3StorageAdapter(
            s3Client: $s3Client,
            bucket: $bucket,
            prefix: $prefix,
            publicUrl: $publicUrl,
        );
    }

    /**
     * Parse bucket and region from DSN host.
     *
     * Supported formats:
     *   {bucket}.s3.{region}.amazonaws.com  → bucket + region
     *   {bucket}.s3.amazonaws.com           → bucket only
     *   {bucket}                            → bucket only (for custom endpoints)
     *
     * @param array<string, mixed> $options
     * @return array{?string, ?string} [bucket, region]
     */
    private function parseHost(Dsn $dsn, array $options): array
    {
        $host = $dsn->getHost();

        if ($host === null) {
            return [$options['bucket'] ?? null, $options['region'] ?? null];
        }

        // {bucket}.s3.{region}.amazonaws.com
        if (preg_match('/^(.+)\.s3\.([a-z0-9-]+)\.amazonaws\.com$/', $host, $matches)) {
            return [
                $matches[1],
                $options['region'] ?? $matches[2],
            ];
        }

        // {bucket}.s3.amazonaws.com
        if (preg_match('/^(.+)\.s3\.amazonaws\.com$/', $host, $matches)) {
            return [
                $matches[1],
                $options['region'] ?? null,
            ];
        }

        // Plain host = bucket name (for custom endpoints like MinIO)
        return [
            $host,
            $options['region'] ?? $dsn->getOption('region'),
        ];
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 's3';
    }

    /**
     * Parse prefix from DSN path.
     *
     * @param array<string, mixed> $options
     */
    private function parsePrefix(Dsn $dsn, array $options): string
    {
        $path = ltrim($dsn->getPath() ?? '', '/');

        if ($path !== '') {
            return $path;
        }

        return $options['prefix'] ?? '';
    }
}
