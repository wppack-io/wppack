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

namespace WPPack\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Adapter\AdapterDefinition;
use WPPack\Component\Cache\Adapter\AdapterField;

#[CoversClass(AdapterDefinition::class)]
#[CoversClass(AdapterField::class)]
final class AdapterDefinitionTest extends TestCase
{
    #[Test]
    public function adapterFieldCarriesAllProperties(): void
    {
        $field = new AdapterField(
            name: 'port',
            label: 'Port',
            type: 'number',
            required: true,
            default: '6379',
            help: 'The Redis server port',
            dsnPart: 'port',
            options: [['label' => '6379', 'value' => '6379']],
            maxWidth: '200px',
            conditional: 'host != ""',
        );

        self::assertSame('port', $field->name);
        self::assertSame('Port', $field->label);
        self::assertSame('number', $field->type);
        self::assertTrue($field->required);
        self::assertSame('6379', $field->default);
        self::assertSame('port', $field->dsnPart);
        self::assertSame([['label' => '6379', 'value' => '6379']], $field->options);
        self::assertSame('200px', $field->maxWidth);
        self::assertSame('host != ""', $field->conditional);
    }

    #[Test]
    public function adapterFieldDefaults(): void
    {
        $field = new AdapterField(name: 'host', label: 'Host');

        self::assertSame('text', $field->type);
        self::assertFalse($field->required);
        self::assertNull($field->default);
        self::assertNull($field->help);
        self::assertNull($field->dsnPart);
        self::assertNull($field->options);
    }

    #[Test]
    public function buildDsnIncludesUserAndPasswordAuth(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', [
            new AdapterField('user', 'User', dsnPart: 'user'),
            new AdapterField('password', 'Password', dsnPart: 'password'),
            new AdapterField('host', 'Host', dsnPart: 'host'),
        ]);

        $dsn = $def->buildDsn([
            'user' => 'admin',
            'password' => 's3c ret!',
            'host' => 'redis.example.com',
        ]);

        self::assertSame('redis://admin:s3c+ret%21@redis.example.com', $dsn);
    }

    #[Test]
    public function buildDsnMergesOptionFields(): void
    {
        $def = new AdapterDefinition('dynamodb', 'DynamoDB', [
            new AdapterField('region', 'Region', dsnPart: 'option:region'),
            new AdapterField('table', 'Table', dsnPart: 'option:table'),
        ]);

        $dsn = $def->buildDsn(['region' => 'us-east-1', 'table' => 'cache']);

        self::assertStringContainsString('region=us-east-1', $dsn);
        self::assertStringContainsString('table=cache', $dsn);
    }

    #[Test]
    public function buildDsnFieldsDefaultIsUsedWhenMissing(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', [
            new AdapterField('host', 'Host', dsnPart: 'host', default: 'localhost'),
            new AdapterField('port', 'Port', dsnPart: 'port', default: '6379'),
        ]);

        self::assertSame('redis://localhost:6379', $def->buildDsn([]));
    }

    #[Test]
    public function buildDsnSkipsFieldsWithoutDsnPart(): void
    {
        $def = new AdapterDefinition('redis', 'Redis', [
            new AdapterField('host', 'Host', dsnPart: 'host'),
            new AdapterField('note', 'Note'),
        ]);

        $dsn = $def->buildDsn(['host' => 'localhost', 'note' => 'ignored']);

        self::assertStringNotContainsString('note', $dsn);
        self::assertStringNotContainsString('ignored', $dsn);
    }

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
