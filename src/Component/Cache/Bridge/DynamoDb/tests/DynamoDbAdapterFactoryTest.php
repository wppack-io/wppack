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

namespace WPPack\Component\Cache\Bridge\DynamoDb\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Adapter\Dsn;
use WPPack\Component\Cache\Bridge\DynamoDb\DynamoDbAdapter;
use WPPack\Component\Cache\Bridge\DynamoDb\DynamoDbAdapterFactory;
use WPPack\Component\Cache\Exception\AdapterException;

final class DynamoDbAdapterFactoryTest extends TestCase
{
    private DynamoDbAdapterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DynamoDbAdapterFactory();
    }

    #[Test]
    public function definitionsReturnsOneDefinition(): void
    {
        $definitions = DynamoDbAdapterFactory::definitions();

        self::assertCount(1, $definitions);
        self::assertSame('dynamodb', $definitions[0]->scheme);
    }

    #[Test]
    public function supportsDynamoDbScheme(): void
    {
        self::assertTrue($this->factory->supports(Dsn::fromString('dynamodb://ap-northeast-1/cache')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        self::assertFalse($this->factory->supports(Dsn::fromString('redis://localhost')));
        self::assertFalse($this->factory->supports(Dsn::fromString('memcached://localhost')));
    }

    #[Test]
    public function createsDynamoDbAdapter(): void
    {
        $adapter = $this->factory->create(Dsn::fromString('dynamodb://ap-northeast-1/my_cache'));

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
        self::assertSame('dynamodb', $adapter->getName());
    }

    #[Test]
    public function defaultTableName(): void
    {
        $adapter = $this->factory->create(Dsn::fromString('dynamodb://ap-northeast-1'));

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function endpointFromDsnOption(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('dynamodb://us-east-1/cache?endpoint=http://localhost:8000'),
        );

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function endpointFromOptions(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('dynamodb://us-east-1/cache'),
            ['endpoint' => 'http://localhost:8000'],
        );

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function keyPrefixFromDsnOption(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('dynamodb://ap-northeast-1/cache?key_prefix=mysite:'),
        );

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function keyPrefixFromOptions(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('dynamodb://ap-northeast-1/cache'),
            ['key_prefix' => 'mysite:'],
        );

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function regionFromOptions(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('dynamodb://ap-northeast-1/cache'),
            ['region' => 'us-west-2'],
        );

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function tableFromOptions(): void
    {
        $adapter = $this->factory->create(
            Dsn::fromString('dynamodb://ap-northeast-1'),
            ['table' => 'my_table'],
        );

        self::assertInstanceOf(DynamoDbAdapter::class, $adapter);
    }

    #[Test]
    public function throwsWhenRegionMissing(): void
    {
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Region is required');

        $this->factory->create(Dsn::fromString('dynamodb:?endpoint=http://localhost:8000'));
    }
}
