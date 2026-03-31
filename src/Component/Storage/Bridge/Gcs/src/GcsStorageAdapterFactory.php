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

namespace WpPack\Component\Storage\Bridge\Gcs;

use Google\Cloud\Storage\Bucket;
use Google\Cloud\Storage\StorageClient;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Adapter\StorageAdapterDefinition;
use WpPack\Component\Storage\Adapter\StorageAdapterFactoryInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterField;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

final class GcsStorageAdapterFactory implements StorageAdapterFactoryInterface
{
    public static function definitions(): array
    {
        return [
            new StorageAdapterDefinition(
                scheme: 'gcs',
                label: 'Google Cloud Storage',
                fields: [
                    new StorageAdapterField('bucket', 'Bucket', required: true, dsnPart: 'host'),
                    new StorageAdapterField('project', 'Project ID', dsnPart: 'option:project'),
                    new StorageAdapterField('keyFile', 'Key File Path', dsnPart: 'option:key_file'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface
    {
        $bucketName = $this->parseBucket($dsn, $options);

        if ($bucketName === null) {
            throw new InvalidArgumentException('Cannot determine bucket name from GCS storage DSN. Use "gcs://{bucket}.storage.googleapis.com/{prefix}" or "gcs://{bucket}" format.');
        }

        $prefix = $this->parsePrefix($dsn, $options);
        $publicUrl = $dsn->getOption('public_url') ?? $options['public_url'] ?? null;
        $project = $dsn->getOption('project') ?? $options['project'] ?? null;
        $keyFile = $dsn->getOption('key_file') ?? $options['key_file'] ?? null;

        if (isset($options['bucket']) && $options['bucket'] instanceof Bucket) {
            $bucket = $options['bucket'];
        } else {
            $clientConfig = [];

            if ($project !== null) {
                $clientConfig['projectId'] = $project;
            }

            if ($keyFile !== null) {
                $clientConfig['keyFilePath'] = $keyFile;
            }

            $storageClient = $options['storage_client'] ?? new StorageClient($clientConfig);
            $bucket = $storageClient->bucket($bucketName);
        }

        return new GcsStorageAdapter(
            bucket: $bucket,
            prefix: $prefix,
            publicUrl: $publicUrl,
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'gcs';
    }

    /**
     * Parse bucket name from DSN host.
     *
     * Supported formats:
     *   {bucket}.storage.googleapis.com → bucket name
     *   {bucket}                        → plain bucket name
     *
     * @param array<string, mixed> $options
     */
    private function parseBucket(Dsn $dsn, array $options): ?string
    {
        $host = $dsn->getHost();

        if ($host === null) {
            return $options['bucket'] ?? null;
        }

        // {bucket}.storage.googleapis.com
        if (preg_match('/^(.+)\.storage\.googleapis\.com$/', $host, $matches)) {
            return $matches[1];
        }

        // Plain host = bucket name
        return $host;
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
