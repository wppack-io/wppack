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

namespace WPPack\Component\Cache\Bridge\DynamoDb;

use AsyncAws\DynamoDb\DynamoDbClient;
use WPPack\Component\Cache\Adapter\AdapterDefinition;
use WPPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WPPack\Component\Cache\Adapter\AdapterField;
use WPPack\Component\Cache\Adapter\AdapterInterface;
use WPPack\Component\Cache\Exception\AdapterException;
use WPPack\Component\Dsn\Dsn;

final class DynamoDbAdapterFactory implements AdapterFactoryInterface
{
    /**
     * @var list<array{label: string, value: string}>
     *
     * @see https://docs.aws.amazon.com/general/latest/gr/ddb.html
     */
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
            new AdapterDefinition(
                scheme: 'dynamodb',
                label: 'Amazon DynamoDB',
                fields: [
                    new AdapterField('region', 'Region', required: true, dsnPart: 'host', options: self::REGION_OPTIONS, maxWidth: '280px'),
                    new AdapterField('table', 'Table Name', required: true, dsnPart: 'path'),
                    new AdapterField('endpoint', 'Endpoint', dsnPart: 'option:endpoint', help: 'Custom endpoint URL (optional)'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): AdapterInterface
    {
        $region = $dsn->getHost() ?? $options['region']
            ?? throw new AdapterException('Region is required for DynamoDB.');

        $table = ltrim($dsn->getPath() ?? '', '/')
            ?: ($options['table'] ?? 'cache');

        $endpoint = $options['endpoint'] ?? $dsn->getOption('endpoint');

        $keyPrefix = $options['key_prefix']
            ?? $dsn->getOption('key_prefix')
            ?? 'wp:';

        return new DynamoDbAdapter($table, $region, $keyPrefix, $endpoint);
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'dynamodb'
            && class_exists(DynamoDbClient::class);
    }
}
