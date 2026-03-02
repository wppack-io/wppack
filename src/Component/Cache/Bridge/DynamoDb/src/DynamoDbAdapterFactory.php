<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\DynamoDb;

use AsyncAws\DynamoDb\DynamoDbClient;
use WpPack\Component\Cache\Adapter\AdapterFactoryInterface;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Exception\AdapterException;

final class DynamoDbAdapterFactory implements AdapterFactoryInterface
{
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
