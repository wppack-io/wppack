<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Bridge\DynamoDb\Tests;

use AsyncAws\DynamoDb\DynamoDbClient;
use AsyncAws\DynamoDb\Input\CreateTableInput;
use AsyncAws\DynamoDb\Input\ScanInput;
use AsyncAws\DynamoDb\ValueObject\AttributeDefinition;
use AsyncAws\DynamoDb\ValueObject\AttributeValue;
use AsyncAws\DynamoDb\ValueObject\KeySchemaElement;
use AsyncAws\DynamoDb\ValueObject\WriteRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Bridge\DynamoDb\DynamoDbAdapter;

final class DynamoDbAdapterTest extends TestCase
{
    private const TABLE = 'wppack_test_cache';
    private const ENDPOINT = 'http://localhost:8000';
    private const REGION = 'us-east-1';
    private const KEY_PREFIX = 'wppack_test:';

    private DynamoDbAdapter $adapter;
    private DynamoDbClient $client;

    protected function setUp(): void
    {
        // DynamoDB Local accepts any credentials but async-aws requires them
        if (!isset($_SERVER['AWS_ACCESS_KEY_ID'])) {
            putenv('AWS_ACCESS_KEY_ID=test');
            putenv('AWS_SECRET_ACCESS_KEY=test');
        }

        $this->client = new DynamoDbClient([
            'region' => self::REGION,
            'endpoint' => self::ENDPOINT,
        ]);

        $this->ensureTableExists();

        $this->adapter = new DynamoDbAdapter(
            self::TABLE,
            self::REGION,
            self::KEY_PREFIX,
            self::ENDPOINT,
        );

        if (!$this->adapter->isAvailable()) {
            self::markTestSkipped('DynamoDB Local is not available at ' . self::ENDPOINT);
        }

        $this->cleanupTable();
    }

    protected function tearDown(): void
    {
        if (isset($this->adapter)) {
            $this->cleanupTable();
        }
    }

    #[Test]
    public function getName(): void
    {
        self::assertSame('dynamodb', $this->adapter->getName());
    }

    #[Test]
    public function setAndGet(): void
    {
        self::assertTrue($this->adapter->set('wppack_test:1:posts:key', 'value'));
        self::assertSame('value', $this->adapter->get('wppack_test:1:posts:key'));
    }

    #[Test]
    public function getReturnsFalseForMissing(): void
    {
        self::assertNull($this->adapter->get('wppack_test:1:posts:nonexistent'));
    }

    #[Test]
    public function getMultiple(): void
    {
        $this->adapter->set('wppack_test:1:posts:key1', 'value1');
        $this->adapter->set('wppack_test:1:posts:key2', 'value2');

        $results = $this->adapter->getMultiple([
            'wppack_test:1:posts:key1',
            'wppack_test:1:posts:key2',
            'wppack_test:1:posts:missing',
        ]);

        self::assertSame('value1', $results['wppack_test:1:posts:key1']);
        self::assertSame('value2', $results['wppack_test:1:posts:key2']);
        self::assertNull($results['wppack_test:1:posts:missing']);
    }

    #[Test]
    public function setMultiple(): void
    {
        $results = $this->adapter->setMultiple([
            'wppack_test:1:posts:key1' => 'value1',
            'wppack_test:1:posts:key2' => 'value2',
        ]);

        self::assertTrue($results['wppack_test:1:posts:key1']);
        self::assertTrue($results['wppack_test:1:posts:key2']);
        self::assertSame('value1', $this->adapter->get('wppack_test:1:posts:key1'));
    }

    #[Test]
    public function addSucceeds(): void
    {
        self::assertTrue($this->adapter->add('wppack_test:1:posts:new', 'value'));
        self::assertSame('value', $this->adapter->get('wppack_test:1:posts:new'));
    }

    #[Test]
    public function addFailsForExisting(): void
    {
        $this->adapter->set('wppack_test:1:posts:existing', 'old');

        self::assertFalse($this->adapter->add('wppack_test:1:posts:existing', 'new'));
        self::assertSame('old', $this->adapter->get('wppack_test:1:posts:existing'));
    }

    #[Test]
    public function delete(): void
    {
        $this->adapter->set('wppack_test:1:posts:key', 'value');
        $this->adapter->delete('wppack_test:1:posts:key');

        self::assertNull($this->adapter->get('wppack_test:1:posts:key'));
    }

    #[Test]
    public function deleteMultiple(): void
    {
        $this->adapter->set('wppack_test:1:posts:key1', 'value1');
        $this->adapter->set('wppack_test:1:posts:key2', 'value2');

        $results = $this->adapter->deleteMultiple([
            'wppack_test:1:posts:key1',
            'wppack_test:1:posts:key2',
        ]);

        self::assertTrue($results['wppack_test:1:posts:key1']);
        self::assertTrue($results['wppack_test:1:posts:key2']);
        self::assertNull($this->adapter->get('wppack_test:1:posts:key1'));
    }

    #[Test]
    public function increment(): void
    {
        $this->adapter->set('wppack_test:1:posts:counter', '10');

        self::assertSame(15, $this->adapter->increment('wppack_test:1:posts:counter', 5));
        self::assertSame('15', $this->adapter->get('wppack_test:1:posts:counter'));
    }

    #[Test]
    public function incrementReturnsFalseForMissing(): void
    {
        self::assertNull($this->adapter->increment('wppack_test:1:posts:nonexistent'));
    }

    #[Test]
    public function decrement(): void
    {
        $this->adapter->set('wppack_test:1:posts:counter', '10');

        self::assertSame(7, $this->adapter->decrement('wppack_test:1:posts:counter', 3));
        self::assertSame('7', $this->adapter->get('wppack_test:1:posts:counter'));
    }

    #[Test]
    public function flushWithPrefix(): void
    {
        $this->adapter->set('wppack_test:1:posts:a', '1');
        $this->adapter->set('wppack_test:1:posts:b', '2');
        $this->adapter->set('wppack_test:1:options:c', '3');

        $this->adapter->flush('wppack_test:1:posts:');

        self::assertNull($this->adapter->get('wppack_test:1:posts:a'));
        self::assertNull($this->adapter->get('wppack_test:1:posts:b'));
        self::assertSame('3', $this->adapter->get('wppack_test:1:options:c'));
    }

    #[Test]
    public function flushWithPrefixPreservesOtherPrefixes(): void
    {
        // Site 1 posts
        $this->adapter->set('wppack_test:1:posts:a', '1');
        // Site 2 posts
        $this->adapter->set('wppack_test:2:posts:a', '2');
        // Site 1 options
        $this->adapter->set('wppack_test:1:options:a', '3');

        // Flush only site 1 posts
        $this->adapter->flush('wppack_test:1:posts:');

        self::assertNull($this->adapter->get('wppack_test:1:posts:a'));
        self::assertSame('2', $this->adapter->get('wppack_test:2:posts:a'));
        self::assertSame('3', $this->adapter->get('wppack_test:1:options:a'));
    }

    #[Test]
    public function flushAll(): void
    {
        $this->adapter->set('wppack_test:1:posts:a', '1');
        $this->adapter->set('wppack_test:1:options:b', '2');

        $this->adapter->flush('');

        self::assertNull($this->adapter->get('wppack_test:1:posts:a'));
        self::assertNull($this->adapter->get('wppack_test:1:options:b'));
    }

    #[Test]
    public function isAvailable(): void
    {
        self::assertTrue($this->adapter->isAvailable());
    }

    #[Test]
    public function isNotAvailableForBadEndpoint(): void
    {
        $adapter = new DynamoDbAdapter(
            self::TABLE,
            self::REGION,
            self::KEY_PREFIX,
            'http://localhost:1',
        );

        self::assertFalse($adapter->isAvailable());
    }

    #[Test]
    public function setWithTtl(): void
    {
        $this->adapter->set('wppack_test:1:posts:ttl', 'value', 60);

        self::assertSame('value', $this->adapter->get('wppack_test:1:posts:ttl'));
    }

    #[Test]
    public function setWithNegativeTtlDeletesKey(): void
    {
        $this->adapter->set('wppack_test:1:posts:neg', 'value');
        self::assertSame('value', $this->adapter->get('wppack_test:1:posts:neg'));

        self::assertTrue($this->adapter->set('wppack_test:1:posts:neg', 'new', -1));
        self::assertNull($this->adapter->get('wppack_test:1:posts:neg'));
    }

    #[Test]
    public function setMultipleWithNegativeTtlDeletesKeys(): void
    {
        $this->adapter->set('wppack_test:1:posts:neg1', 'value1');
        $this->adapter->set('wppack_test:1:posts:neg2', 'value2');

        $results = $this->adapter->setMultiple([
            'wppack_test:1:posts:neg1' => 'new1',
            'wppack_test:1:posts:neg2' => 'new2',
        ], -1);

        self::assertTrue($results['wppack_test:1:posts:neg1']);
        self::assertTrue($results['wppack_test:1:posts:neg2']);
        self::assertNull($this->adapter->get('wppack_test:1:posts:neg1'));
        self::assertNull($this->adapter->get('wppack_test:1:posts:neg2'));
    }

    #[Test]
    public function addWithNegativeTtlIsNoop(): void
    {
        $this->adapter->set('wppack_test:1:posts:existing', 'old');

        self::assertTrue($this->adapter->add('wppack_test:1:posts:existing', 'new', -1));
        self::assertSame('old', $this->adapter->get('wppack_test:1:posts:existing'));
    }

    #[Test]
    public function addWithTtl(): void
    {
        self::assertTrue($this->adapter->add('wppack_test:1:posts:ttl_add', 'value', 60));
        self::assertSame('value', $this->adapter->get('wppack_test:1:posts:ttl_add'));
    }

    #[Test]
    public function incrementPreservesTtl(): void
    {
        $this->adapter->set('wppack_test:1:posts:counter_ttl', '10', 3600);

        self::assertSame(15, $this->adapter->increment('wppack_test:1:posts:counter_ttl', 5));
        // Value should still be retrievable (TTL preserved, not expired)
        self::assertSame('15', $this->adapter->get('wppack_test:1:posts:counter_ttl'));
    }

    #[Test]
    public function getReturnsFalseForExpiredItem(): void
    {
        $this->adapter->set('wppack_test:1:posts:expire', 'value', 1);
        self::assertSame('value', $this->adapter->get('wppack_test:1:posts:expire'));

        sleep(2);

        self::assertNull($this->adapter->get('wppack_test:1:posts:expire'));
    }

    #[Test]
    public function splitKeyWithNoSecondColon(): void
    {
        // Key like "wppack_test:nosecondcolon" has only one colon after prefix.
        // splitKey returns [$fullKey, ''] when there is no second colon.
        // DynamoDB does not allow empty string sort keys, so an exception is expected.
        $this->expectException(\WpPack\Component\Cache\Exception\AdapterException::class);

        $this->adapter->set('wppack_test:nosecondcolon', 'value');
    }

    #[Test]
    public function setMultipleBatchesOver25(): void
    {
        $values = [];
        for ($i = 0; $i < 26; $i++) {
            $values[sprintf('wppack_test:1:posts:batch_set_%d', $i)] = sprintf('value_%d', $i);
        }

        $results = $this->adapter->setMultiple($values);

        self::assertCount(26, $results);
        foreach ($results as $result) {
            self::assertTrue($result);
        }

        // Verify a few items were stored correctly
        self::assertSame('value_0', $this->adapter->get('wppack_test:1:posts:batch_set_0'));
        self::assertSame('value_25', $this->adapter->get('wppack_test:1:posts:batch_set_25'));
    }

    #[Test]
    public function deleteMultipleBatchesOver25(): void
    {
        $values = [];
        $keys = [];
        for ($i = 0; $i < 26; $i++) {
            $key = sprintf('wppack_test:1:posts:batch_del_%d', $i);
            $values[$key] = sprintf('value_%d', $i);
            $keys[] = $key;
        }

        $this->adapter->setMultiple($values);

        $results = $this->adapter->deleteMultiple($keys);

        self::assertCount(26, $results);
        foreach ($results as $result) {
            self::assertTrue($result);
        }

        // Verify items were deleted
        self::assertNull($this->adapter->get('wppack_test:1:posts:batch_del_0'));
        self::assertNull($this->adapter->get('wppack_test:1:posts:batch_del_25'));
    }

    #[Test]
    public function getMultipleEmpty(): void
    {
        $results = $this->adapter->getMultiple([]);

        self::assertSame([], $results);
    }

    private function ensureTableExists(): void
    {
        try {
            $this->client->describeTable(['TableName' => self::TABLE]);
        } catch (\Throwable) {
            try {
                $this->client->createTable(new CreateTableInput([
                    'TableName' => self::TABLE,
                    'KeySchema' => [
                        new KeySchemaElement(['AttributeName' => 'p', 'KeyType' => 'HASH']),
                        new KeySchemaElement(['AttributeName' => 'k', 'KeyType' => 'RANGE']),
                    ],
                    'AttributeDefinitions' => [
                        new AttributeDefinition(['AttributeName' => 'p', 'AttributeType' => 'S']),
                        new AttributeDefinition(['AttributeName' => 'k', 'AttributeType' => 'S']),
                    ],
                    'BillingMode' => 'PAY_PER_REQUEST',
                ]));
            } catch (\Throwable) {
                self::markTestSkipped('DynamoDB Local is not available at ' . self::ENDPOINT);
            }
        }
    }

    private function cleanupTable(): void
    {
        try {
            $result = $this->client->scan(new ScanInput([
                'TableName' => self::TABLE,
                'ProjectionExpression' => 'p, k',
            ]));

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
                    $this->client->batchWriteItem([
                        'RequestItems' => [self::TABLE => $requests],
                    ]);
                    $requests = [];
                }
            }

            if ($requests !== []) {
                $this->client->batchWriteItem([
                    'RequestItems' => [self::TABLE => $requests],
                ]);
            }
        } catch (\Throwable) {
            // Ignore cleanup errors
        }
    }
}
