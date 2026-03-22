<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\DynamoDb;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\DeleteItemInput;
use AsyncAws\DynamoDb\Input\DescribeTableInput;
use AsyncAws\DynamoDb\Input\GetItemInput;
use AsyncAws\DynamoDb\Input\PutItemInput;
use AsyncAws\DynamoDb\Input\QueryInput;
use AsyncAws\DynamoDb\Input\ScanInput;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\WriteRequest;
use WpPack\Component\Cache\Adapter\AbstractAdapter;

final class DynamoDbAdapter extends AbstractAdapter
{
    private DynamoDbClient $client;

    public function __construct(
        private readonly string $table,
        string $region,
        private readonly string $keyPrefix = 'wp:',
        ?string $endpoint = null,
    ) {
        $config = ['region' => $region];
        if ($endpoint !== null) {
            $config['endpoint'] = $endpoint;
        }
        $this->client = new DynamoDbClient($config);
    }

    public function getName(): string
    {
        return 'dynamodb';
    }

    protected function doGet(string $key): ?string
    {
        [$pk, $sk] = $this->splitKey($key);

        $result = $this->client->getItem(new GetItemInput([
            'TableName' => $this->table,
            'Key' => [
                'p' => new AttributeValue(['S' => $pk]),
                'k' => new AttributeValue(['S' => $sk]),
            ],
            'ConsistentRead' => true,
        ]));

        $item = $result->getItem();

        if ($item === []) {
            return null;
        }

        if ($this->isExpired($item)) {
            return null;
        }

        return $item['v']->getS();
    }

    protected function doGetMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->doGet($key);
        }

        return $results;
    }

    protected function doSet(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            $this->doDelete($key);

            return true;
        }

        [$pk, $sk] = $this->splitKey($key);

        $item = [
            'p' => new AttributeValue(['S' => $pk]),
            'k' => new AttributeValue(['S' => $sk]),
            'v' => new AttributeValue(['S' => $value]),
        ];

        if ($ttl > 0) {
            $item['t'] = new AttributeValue(['N' => (string) (time() + $ttl)]);
        }

        $this->client->putItem(new PutItemInput([
            'TableName' => $this->table,
            'Item' => $item,
        ]));

        return true;
    }

    protected function doSetMultiple(array $values, int $ttl = 0): array
    {
        if ($values === []) {
            return [];
        }

        $results = [];
        $requests = [];

        if ($ttl < 0) {
            return $this->doDeleteMultiple(array_keys($values));
        }

        foreach ($values as $key => $value) {
            [$pk, $sk] = $this->splitKey($key);

            $item = [
                'p' => new AttributeValue(['S' => $pk]),
                'k' => new AttributeValue(['S' => $sk]),
                'v' => new AttributeValue(['S' => $value]),
            ];

            if ($ttl > 0) {
                $item['t'] = new AttributeValue(['N' => (string) (time() + $ttl)]);
            }

            $requests[] = new WriteRequest(['PutRequest' => ['Item' => $item]]);
            $results[$key] = true;

            if (\count($requests) >= 25) {
                $this->batchWrite($requests);
                $requests = [];
            }
        }

        if ($requests !== []) {
            $this->batchWrite($requests);
        }

        return $results;
    }

    protected function doAdd(string $key, string $value, int $ttl = 0): bool
    {
        if ($ttl < 0) {
            return true;
        }

        [$pk, $sk] = $this->splitKey($key);

        $item = [
            'p' => new AttributeValue(['S' => $pk]),
            'k' => new AttributeValue(['S' => $sk]),
            'v' => new AttributeValue(['S' => $value]),
        ];

        if ($ttl > 0) {
            $item['t'] = new AttributeValue(['N' => (string) (time() + $ttl)]);
        }

        try {
            $this->client->putItem(new PutItemInput([
                'TableName' => $this->table,
                'Item' => $item,
                'ConditionExpression' => 'attribute_not_exists(p) OR t < :now',
                'ExpressionAttributeValues' => [
                    ':now' => new AttributeValue(['N' => (string) time()]),
                ],
            ]));

            return true;
        } catch (\AsyncAws\DynamoDb\Exception\ConditionalCheckFailedException) {
            return false;
        }
    }

    protected function doDelete(string $key): bool
    {
        [$pk, $sk] = $this->splitKey($key);

        $this->client->deleteItem(new DeleteItemInput([
            'TableName' => $this->table,
            'Key' => [
                'p' => new AttributeValue(['S' => $pk]),
                'k' => new AttributeValue(['S' => $sk]),
            ],
        ]));

        return true;
    }

    protected function doDeleteMultiple(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $results = [];
        $requests = [];

        foreach ($keys as $key) {
            [$pk, $sk] = $this->splitKey($key);

            $requests[] = new WriteRequest([
                'DeleteRequest' => [
                    'Key' => [
                        'p' => new AttributeValue(['S' => $pk]),
                        'k' => new AttributeValue(['S' => $sk]),
                    ],
                ],
            ]);
            $results[$key] = true;

            if (\count($requests) >= 25) {
                $this->batchWrite($requests);
                $requests = [];
            }
        }

        if ($requests !== []) {
            $this->batchWrite($requests);
        }

        return $results;
    }

    protected function doIncrement(string $key, int $offset = 1): ?int
    {
        [$pk, $sk] = $this->splitKey($key);

        $result = $this->client->getItem(new GetItemInput([
            'TableName' => $this->table,
            'Key' => [
                'p' => new AttributeValue(['S' => $pk]),
                'k' => new AttributeValue(['S' => $sk]),
            ],
            'ConsistentRead' => true,
        ]));

        $item = $result->getItem();

        if ($item === [] || $this->isExpired($item)) {
            return null;
        }

        $currentValue = (int) ($item['v']->getS() ?? '0');
        $newValue = $currentValue + $offset;

        $putItem = [
            'p' => new AttributeValue(['S' => $pk]),
            'k' => new AttributeValue(['S' => $sk]),
            'v' => new AttributeValue(['S' => (string) $newValue]),
        ];

        if (isset($item['t'])) {
            $putItem['t'] = $item['t'];
        }

        $this->client->putItem(new PutItemInput([
            'TableName' => $this->table,
            'Item' => $putItem,
        ]));

        return $newValue;
    }

    protected function doDecrement(string $key, int $offset = 1): ?int
    {
        return $this->doIncrement($key, -$offset);
    }

    protected function doFlush(string $prefix = ''): bool
    {
        if ($prefix === '') {
            return $this->flushAll();
        }

        return $this->flushByPrefix($prefix);
    }

    public function isAvailable(): bool
    {
        try {
            $this->client->describeTable(new DescribeTableInput([
                'TableName' => $this->table,
            ]));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function close(): void
    {
        // HTTP-based client — no persistent connection to close
    }

    /**
     * @return array{string, string}
     */
    private function splitKey(string $fullKey): array
    {
        $rest = substr($fullKey, \strlen($this->keyPrefix));
        $firstColon = strpos($rest, ':');

        if ($firstColon === false) {
            return [$fullKey, ''];
        }

        $secondColon = strpos($rest, ':', $firstColon + 1);

        if ($secondColon === false) {
            return [$fullKey, ''];
        }

        $splitPos = \strlen($this->keyPrefix) + $secondColon;

        return [substr($fullKey, 0, $splitPos), substr($fullKey, $splitPos + 1)];
    }

    /**
     * @param array<string, AttributeValue> $item
     */
    private function isExpired(array $item): bool
    {
        if (!isset($item['t'])) {
            return false;
        }

        $ttl = (int) ($item['t']->getN() ?? '0');

        return $ttl > 0 && time() > $ttl;
    }

    /**
     * @param list<WriteRequest> $requests
     */
    private function batchWrite(array $requests): void
    {
        $this->client->batchWriteItem([
            'RequestItems' => [
                $this->table => $requests,
            ],
        ]);
    }

    private function flushAll(): bool
    {
        $exclusiveStartKey = null;

        do {
            $input = [
                'TableName' => $this->table,
                'ProjectionExpression' => 'p, k',
            ];

            if ($exclusiveStartKey !== null) {
                $input['ExclusiveStartKey'] = $exclusiveStartKey;
            }

            $result = $this->client->scan(new ScanInput($input));
            $requests = [];

            foreach ($result->getItems() as $item) {
                $requests[] = new WriteRequest([
                    'DeleteRequest' => [
                        'Key' => [
                            'p' => $item['p'],
                            'k' => $item['k'],
                        ],
                    ],
                ]);

                if (\count($requests) >= 25) {
                    $this->batchWrite($requests);
                    $requests = [];
                }
            }

            if ($requests !== []) {
                $this->batchWrite($requests);
            }

            $exclusiveStartKey = $result->getLastEvaluatedKey();
        } while ($exclusiveStartKey !== []);

        return true;
    }

    private function flushByPrefix(string $prefix): bool
    {
        $pk = rtrim($prefix, ':');

        $exclusiveStartKey = null;

        do {
            $input = [
                'TableName' => $this->table,
                'KeyConditionExpression' => 'p = :pk',
                'ExpressionAttributeValues' => [
                    ':pk' => new AttributeValue(['S' => $pk]),
                ],
                'ProjectionExpression' => 'p, k',
            ];

            if ($exclusiveStartKey !== null) {
                $input['ExclusiveStartKey'] = $exclusiveStartKey;
            }

            $result = $this->client->query(new QueryInput($input));
            $requests = [];

            foreach ($result->getItems() as $item) {
                $requests[] = new WriteRequest([
                    'DeleteRequest' => [
                        'Key' => [
                            'p' => $item['p'],
                            'k' => $item['k'],
                        ],
                    ],
                ]);

                if (\count($requests) >= 25) {
                    $this->batchWrite($requests);
                    $requests = [];
                }
            }

            if ($requests !== []) {
                $this->batchWrite($requests);
            }

            $exclusiveStartKey = $result->getLastEvaluatedKey();
        } while ($exclusiveStartKey !== []);

        return true;
    }
}
