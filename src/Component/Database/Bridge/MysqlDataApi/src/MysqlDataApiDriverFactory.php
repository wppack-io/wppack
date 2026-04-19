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

namespace WPPack\Component\Database\Bridge\MysqlDataApi;

use AsyncAws\RdsDataService\RdsDataServiceClient;
use WPPack\Component\Database\Driver\DriverDefinition;
use WPPack\Component\Database\Driver\DriverFactoryInterface;
use WPPack\Component\Database\Driver\DriverField;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Dsn\Dsn;

/**
 * Factory for Aurora MySQL Data API connections.
 *
 * DSN: mysql+dataapi://cluster-arn/dbname?secret_arn=xxx&region=us-east-1
 */
class MysqlDataApiDriverFactory implements DriverFactoryInterface
{
    public static function definitions(): array
    {
        return [
            new DriverDefinition(
                scheme: 'mysql+dataapi',
                label: 'Aurora MySQL Data API',
                fields: [
                    new DriverField('resource_arn', 'Cluster ARN', required: true, dsnPart: 'host'),
                    new DriverField('secret_arn', 'Secret ARN', required: true),
                    new DriverField('database', 'Database', required: true, dsnPart: 'path'),
                    new DriverField('region', 'AWS Region', default: 'us-east-1'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        $resourceArn = $dsn->getHost() ?? '';
        $database = ltrim($dsn->getPath() ?? '', '/');
        $secretArn = $dsn->getOption('secret_arn', '') ?? '';
        $region = $options['region'] ?? $dsn->getOption('region') ?? $this->extractRegionFromArn($resourceArn) ?? 'us-east-1';

        $client = new RdsDataServiceClient([
            'region' => $region,
        ]);

        return new MysqlDataApiDriver(
            client: $client,
            resourceArn: $resourceArn,
            secretArn: $secretArn,
            database: $database,
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'mysql+dataapi'
            && class_exists(RdsDataServiceClient::class);
    }

    private function extractRegionFromArn(string $arn): ?string
    {
        if (preg_match('/^arn:aws:rds:([^:]+):/', $arn, $m)) {
            return $m[1];
        }

        return null;
    }
}
