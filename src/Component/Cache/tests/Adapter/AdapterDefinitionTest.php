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

namespace WpPack\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\AdapterDefinition;
use WpPack\Component\Cache\Adapter\AdapterField;

#[CoversClass(AdapterDefinition::class)]
final class AdapterDefinitionTest extends TestCase
{
    #[Test]
    public function buildDsnBasic(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', [
            new AdapterField('host', 'Host', dsnPart: 'host'),
            new AdapterField('port', 'Port', dsnPart: 'port'),
            new AdapterField('pass', 'Pass', type: 'password', dsnPart: 'password'),
        ]);
        self::assertSame('redis://:secret@127.0.0.1:6379', $def->buildDsn(['host' => '127.0.0.1', 'port' => '6379', 'pass' => 'secret']));
    }

    #[Test]
    public function buildDsnWithMultiHost(): void
    {
        $def = new AdapterDefinition('redis', 'Cluster', [
            new AdapterField('nodes', 'Nodes', type: 'textarea', dsnPart: 'hosts'),
        ], dsnScheme: 'redis', extraOptions: ['redis_cluster' => '1']);
        $dsn = $def->buildDsn(['nodes' => "node1:6379\nnode2:6379"]);
        self::assertStringStartsWith('redis://', $dsn);
        self::assertStringContainsString('host[node1%3A6379]', $dsn);
        self::assertStringContainsString('redis_cluster=1', $dsn);
    }

    #[Test]
    public function buildDsnWithBooleanTrue(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', [
            new AdapterField('iam', 'IAM', type: 'boolean', dsnPart: 'option:iam_auth'),
        ]);
        self::assertStringContainsString('iam_auth=1', $def->buildDsn(['iam' => true]));
    }

    #[Test]
    public function buildDsnWithBooleanFalseSkipped(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', [
            new AdapterField('iam', 'IAM', type: 'boolean', dsnPart: 'option:iam_auth'),
        ]);
        self::assertStringNotContainsString('iam_auth', $def->buildDsn(['iam' => false]));
    }

    #[Test]
    public function buildDsnWithDsnSchemeOverride(): void
    {
        $def = new AdapterDefinition('rediss-cluster', 'TLS Cluster', [], dsnScheme: 'rediss', extraOptions: ['redis_cluster' => '1']);
        $dsn = $def->buildDsn([]);
        self::assertStringStartsWith('rediss://', $dsn);
        self::assertStringContainsString('redis_cluster=1', $dsn);
    }

    #[Test]
    public function buildDsnWithPath(): void
    {
        $def = new AdapterDefinition('dynamodb', 'DynamoDB', [
            new AdapterField('region', 'Region', dsnPart: 'host'),
            new AdapterField('table', 'Table', dsnPart: 'path'),
        ]);
        self::assertSame('dynamodb://us-east-1/cache', $def->buildDsn(['region' => 'us-east-1', 'table' => 'cache']));
    }

    #[Test]
    public function capabilitiesProperty(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', capabilities: ['compression', 'serializer']);
        self::assertSame(['compression', 'serializer'], $def->capabilities);
    }

    #[Test]
    public function emptyFieldsProduceDefaultDsn(): void
    {
        $def = new AdapterDefinition('apcu', 'APCu');
        self::assertSame('apcu://default', $def->buildDsn([]));
    }
}
