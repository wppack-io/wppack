<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\Adapter\Dsn;
use WpPack\Component\Cache\Exception\InvalidArgumentException;

final class DsnTest extends TestCase
{
    #[Test]
    public function parsesStandardRedisUrl(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1:6379');

        self::assertSame('redis', $dsn->getScheme());
        self::assertSame('127.0.0.1', $dsn->getHost());
        self::assertSame(6379, $dsn->getPort());
        self::assertNull($dsn->getUser());
        self::assertNull($dsn->getPassword());
        self::assertNull($dsn->getPath());
    }

    #[Test]
    public function parsesRedisWithAuth(): void
    {
        $dsn = Dsn::fromString('redis://secret@127.0.0.1:6379');

        self::assertSame('secret', $dsn->getUser());
        self::assertNull($dsn->getPassword());
    }

    #[Test]
    public function parsesRedisWithDbIndex(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1:6379/2');

        self::assertSame('/2', $dsn->getPath());
    }

    #[Test]
    public function parsesRedisTls(): void
    {
        $dsn = Dsn::fromString('rediss://127.0.0.1:6380');

        self::assertSame('rediss', $dsn->getScheme());
        self::assertSame('127.0.0.1', $dsn->getHost());
        self::assertSame(6380, $dsn->getPort());
    }

    #[Test]
    public function parsesValkeyScheme(): void
    {
        $dsn = Dsn::fromString('valkey://127.0.0.1:6379');

        self::assertSame('valkey', $dsn->getScheme());
    }

    #[Test]
    public function parsesValkeysScheme(): void
    {
        $dsn = Dsn::fromString('valkeys://127.0.0.1:6380');

        self::assertSame('valkeys', $dsn->getScheme());
    }

    #[Test]
    public function parsesUnixSocket(): void
    {
        $dsn = Dsn::fromString('redis:///var/run/redis.sock');

        self::assertSame('redis', $dsn->getScheme());
        self::assertNull($dsn->getHost());
        self::assertSame('/var/run/redis.sock', $dsn->getPath());
    }

    #[Test]
    public function parsesQueryParams(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1:6379?timeout=5&read_timeout=3');

        self::assertSame('5', $dsn->getOption('timeout'));
        self::assertSame('3', $dsn->getOption('read_timeout'));
    }

    #[Test]
    public function getOptionReturnsDefault(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1:6379');

        self::assertSame('30', $dsn->getOption('timeout', '30'));
        self::assertNull($dsn->getOption('nonexistent'));
    }

    #[Test]
    public function parsesMultiHostClusterDsn(): void
    {
        $dsn = Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]&host[node3:6379]&redis_cluster=1');

        self::assertSame('redis', $dsn->getScheme());
        self::assertNull($dsn->getHost());
        self::assertSame(['node1:6379', 'node2:6379', 'node3:6379'], $dsn->getArrayOption('host'));
        self::assertSame('1', $dsn->getOption('redis_cluster'));
    }

    #[Test]
    public function parsesSentinelDsn(): void
    {
        $dsn = Dsn::fromString('redis:?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster');

        self::assertSame(['sentinel1:26379', 'sentinel2:26379'], $dsn->getArrayOption('host'));
        self::assertSame('mymaster', $dsn->getOption('redis_sentinel'));
    }

    #[Test]
    public function getArrayOptionReturnsSingleValueAsArray(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1:6379?timeout=5');

        self::assertSame(['5'], $dsn->getArrayOption('timeout'));
    }

    #[Test]
    public function getArrayOptionReturnsEmptyForMissing(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1:6379');

        self::assertSame([], $dsn->getArrayOption('host'));
    }

    #[Test]
    public function getOptionReturnsDefaultForArrayValue(): void
    {
        $dsn = Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]');

        self::assertNull($dsn->getOption('host'));
        self::assertSame('fallback', $dsn->getOption('host', 'fallback'));
    }

    #[Test]
    public function throwsOnInvalidDsn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('not-a-valid-dsn');
    }

    #[Test]
    public function throwsOnMissingScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('127.0.0.1:6379');
    }

    #[Test]
    public function parsesClusterWithAuth(): void
    {
        $dsn = Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]&redis_cluster=1&auth=secret');

        self::assertSame('secret', $dsn->getOption('auth'));
        self::assertSame(['node1:6379', 'node2:6379'], $dsn->getArrayOption('host'));
    }

    #[Test]
    public function parsesSentinelWithMasterPassword(): void
    {
        $dsn = Dsn::fromString('redis://master-pass@?host[sentinel1:26379]&host[sentinel2:26379]&redis_sentinel=mymaster');

        self::assertSame('master-pass', $dsn->getUser());
        self::assertSame('mymaster', $dsn->getOption('redis_sentinel'));
    }

    #[Test]
    public function parsesUnixSocketWithQueryString(): void
    {
        $dsn = Dsn::fromString('redis:///var/run/redis.sock?db=2');

        self::assertSame('redis', $dsn->getScheme());
        self::assertNull($dsn->getHost());
        self::assertSame('/var/run/redis.sock', $dsn->getPath());
        self::assertSame('2', $dsn->getOption('db'));
    }

    #[Test]
    public function parsesHostWithNonNumericPortSuffix(): void
    {
        $dsn = Dsn::fromString('redis://hostname:notaport/2');

        self::assertSame('redis', $dsn->getScheme());
        self::assertSame('hostname:notaport', $dsn->getHost());
        self::assertNull($dsn->getPort());
        self::assertSame('/2', $dsn->getPath());
    }

    #[Test]
    public function parsesUserWithPassword(): void
    {
        $dsn = Dsn::fromString('redis://user:password@127.0.0.1:6379');

        self::assertSame('user', $dsn->getUser());
        self::assertSame('password', $dsn->getPassword());
        self::assertSame('127.0.0.1', $dsn->getHost());
        self::assertSame(6379, $dsn->getPort());
    }

    #[Test]
    public function throwsOnMalformedDsnWithoutScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('invalid-dsn');
    }

    #[Test]
    public function parsesQueryWithKeyOnly(): void
    {
        $dsn = Dsn::fromString('redis://127.0.0.1?flag');

        self::assertSame('', $dsn->getOption('flag'));
    }

    #[Test]
    public function parsesHostWithoutPortOrPath(): void
    {
        $dsn = Dsn::fromString('redis://hostname');

        self::assertSame('redis', $dsn->getScheme());
        self::assertSame('hostname', $dsn->getHost());
        self::assertNull($dsn->getPort());
        self::assertNull($dsn->getPath());
    }

    #[Test]
    public function parsesEmptyPairsInQuery(): void
    {
        $dsn = Dsn::fromString('redis://host?&timeout=5&');

        self::assertSame('5', $dsn->getOption('timeout'));
        self::assertNull($dsn->getOption(''));
    }

    #[Test]
    public function existingScalarConvertedToArrayInQuery(): void
    {
        $dsn = Dsn::fromString('redis://host?timeout=5&timeout[]=3');

        self::assertSame(['5', '3'], $dsn->getArrayOption('timeout'));
        self::assertNull($dsn->getOption('timeout'));
    }
}
