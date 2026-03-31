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
use WpPack\Component\Storage\Adapter\StorageAdapterDefinition;
use WpPack\Component\Storage\Adapter\StorageAdapterFactoryInterface;
use WpPack\Component\Storage\Adapter\StorageAdapterField;
use WpPack\Component\Storage\Adapter\StorageAdapterInterface;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

final class S3StorageAdapterFactory implements StorageAdapterFactoryInterface
{
    /** @var list<array{label: string, value: string}> */
    private const REGION_OPTIONS = [
        ['label' => 'us-east-1 (N. Virginia)', 'value' => 'us-east-1'],
        ['label' => 'us-east-2 (Ohio)', 'value' => 'us-east-2'],
        ['label' => 'us-west-1 (N. California)', 'value' => 'us-west-1'],
        ['label' => 'us-west-2 (Oregon)', 'value' => 'us-west-2'],
        ['label' => 'af-south-1 (Cape Town)', 'value' => 'af-south-1'],
        ['label' => 'ap-east-1 (Hong Kong)', 'value' => 'ap-east-1'],
        ['label' => 'ap-east-2 (Taipei)', 'value' => 'ap-east-2'],
        ['label' => 'ap-south-1 (Mumbai)', 'value' => 'ap-south-1'],
        ['label' => 'ap-south-2 (Hyderabad)', 'value' => 'ap-south-2'],
        ['label' => 'ap-northeast-1 (Tokyo)', 'value' => 'ap-northeast-1'],
        ['label' => 'ap-northeast-2 (Seoul)', 'value' => 'ap-northeast-2'],
        ['label' => 'ap-northeast-3 (Osaka)', 'value' => 'ap-northeast-3'],
        ['label' => 'ap-southeast-1 (Singapore)', 'value' => 'ap-southeast-1'],
        ['label' => 'ap-southeast-2 (Sydney)', 'value' => 'ap-southeast-2'],
        ['label' => 'ap-southeast-3 (Jakarta)', 'value' => 'ap-southeast-3'],
        ['label' => 'ap-southeast-4 (Melbourne)', 'value' => 'ap-southeast-4'],
        ['label' => 'ap-southeast-5 (Malaysia)', 'value' => 'ap-southeast-5'],
        ['label' => 'ap-southeast-6 (New Zealand)', 'value' => 'ap-southeast-6'],
        ['label' => 'ap-southeast-7 (Thailand)', 'value' => 'ap-southeast-7'],
        ['label' => 'ca-central-1 (Canada)', 'value' => 'ca-central-1'],
        ['label' => 'ca-west-1 (Calgary)', 'value' => 'ca-west-1'],
        ['label' => 'eu-central-1 (Frankfurt)', 'value' => 'eu-central-1'],
        ['label' => 'eu-central-2 (Zurich)', 'value' => 'eu-central-2'],
        ['label' => 'eu-north-1 (Stockholm)', 'value' => 'eu-north-1'],
        ['label' => 'eu-south-1 (Milan)', 'value' => 'eu-south-1'],
        ['label' => 'eu-south-2 (Spain)', 'value' => 'eu-south-2'],
        ['label' => 'eu-west-1 (Ireland)', 'value' => 'eu-west-1'],
        ['label' => 'eu-west-2 (London)', 'value' => 'eu-west-2'],
        ['label' => 'eu-west-3 (Paris)', 'value' => 'eu-west-3'],
        ['label' => 'il-central-1 (Tel Aviv)', 'value' => 'il-central-1'],
        ['label' => 'me-central-1 (UAE)', 'value' => 'me-central-1'],
        ['label' => 'me-south-1 (Bahrain)', 'value' => 'me-south-1'],
        ['label' => 'mx-central-1 (Mexico)', 'value' => 'mx-central-1'],
        ['label' => 'sa-east-1 (São Paulo)', 'value' => 'sa-east-1'],
        ['label' => 'us-gov-east-1 (GovCloud US-East)', 'value' => 'us-gov-east-1'],
        ['label' => 'us-gov-west-1 (GovCloud US-West)', 'value' => 'us-gov-west-1'],
    ];

    public static function definitions(): array
    {
        return [
            new StorageAdapterDefinition(
                scheme: 's3',
                label: 'Amazon S3',
                fields: [
                    new StorageAdapterField('bucket', 'Bucket', required: true, dsnPart: 'host'),
                    new StorageAdapterField('region', 'Region', required: true, dsnPart: 'option:region', options: self::REGION_OPTIONS, maxWidth: '280px'),
                    new StorageAdapterField('accessKey', 'Access Key ID', dsnPart: 'user', help: 'Leave empty to use IAM role'),
                    new StorageAdapterField('secretKey', 'Secret Access Key', type: 'password', dsnPart: 'password'),
                    new StorageAdapterField('endpoint', 'Endpoint', dsnPart: 'option:endpoint', help: 'Custom endpoint for MinIO, R2, LocalStack'),
                ],
            ),
        ];
    }

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
