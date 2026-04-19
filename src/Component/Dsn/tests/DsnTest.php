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

namespace WPPack\Component\Dsn\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Dsn\Exception\InvalidDsnException;

final class DsnTest extends TestCase
{
    // ── Standard URI ──

    #[Test]
    public function parseMySQLDsn(): void
    {
        $dsn = Dsn::fromString('mysql://user:pass@host:3306/dbname');

        self::assertSame('mysql', $dsn->getScheme());
        self::assertSame('host', $dsn->getHost());
        self::assertSame('user', $dsn->getUser());
        self::assertSame('pass', $dsn->getPassword());
        self::assertSame(3306, $dsn->getPort());
        self::assertSame('/dbname', $dsn->getPath());
    }

    #[Test]
    public function parseWithQueryParameters(): void
    {
        $dsn = Dsn::fromString('mysql://host/dbname?charset=utf8mb4&timeout=5');

        self::assertSame('utf8mb4', $dsn->getOption('charset'));
        self::assertSame('5', $dsn->getOption('timeout'));
        self::assertNull($dsn->getOption('missing'));
        self::assertSame('default', $dsn->getOption('missing', 'default'));
    }

    #[Test]
    public function parseMariadbScheme(): void
    {
        $dsn = Dsn::fromString('mariadb://user:pass@host:3306/dbname');

        self::assertSame('mariadb', $dsn->getScheme());
        self::assertSame('host', $dsn->getHost());
    }

    #[Test]
    public function parsePostgreSQLDsn(): void
    {
        $dsn = Dsn::fromString('pgsql://user:pass@host:5432/dbname');

        self::assertSame('pgsql', $dsn->getScheme());
        self::assertSame(5432, $dsn->getPort());
        self::assertSame('/dbname', $dsn->getPath());
    }

    // ── SQLite ──

    #[Test]
    public function parseSqlitePath(): void
    {
        $dsn = Dsn::fromString('sqlite:///path/to/database.db');

        self::assertSame('sqlite', $dsn->getScheme());
        self::assertNull($dsn->getHost());
        self::assertSame('/path/to/database.db', $dsn->getPath());
    }

    #[Test]
    public function parseSqliteMemory(): void
    {
        $dsn = Dsn::fromString('sqlite:///:memory:');

        self::assertSame('sqlite', $dsn->getScheme());
        self::assertSame('/:memory:', $dsn->getPath());
    }

    // ── AWS ──

    #[Test]
    public function parseMySQLDataApi(): void
    {
        $dsn = Dsn::fromString('mysql+dataapi://arn:aws:rds:us-east-1:123456789:cluster:my-cluster/mydb?secret_arn=arn:aws:secretsmanager:us-east-1:123456789:secret:my-secret');

        self::assertSame('mysql+dataapi', $dsn->getScheme());
        self::assertStringContainsString('secret', $dsn->getOption('secret_arn'));
    }

    #[Test]
    public function parsePostgreSQLDataApi(): void
    {
        $dsn = Dsn::fromString('pgsql+dataapi://arn:aws:rds:us-east-1:123456789:cluster:my-cluster/mydb?secret_arn=arn:aws:secretsmanager:us-east-1:123456789:secret:my-secret&region=us-east-1');

        self::assertSame('pgsql+dataapi', $dsn->getScheme());
        self::assertSame('us-east-1', $dsn->getOption('region'));
    }

    #[Test]
    public function parseAuroraDSQL(): void
    {
        $dsn = Dsn::fromString('dsql://admin:token@abc123.dsql.us-east-1.on.aws/mydb');

        self::assertSame('dsql', $dsn->getScheme());
        self::assertSame('abc123.dsql.us-east-1.on.aws', $dsn->getHost());
        self::assertSame('admin', $dsn->getUser());
        self::assertSame('token', $dsn->getPassword());
        self::assertSame('/mydb', $dsn->getPath());

        // Region can be extracted from host
        preg_match('/\.dsql\.(.+?)\.on\.aws$/', $dsn->getHost(), $m);
        self::assertSame('us-east-1', $m[1]);
    }

    // ── WordPress ──

    #[Test]
    public function parseWpdbDefault(): void
    {
        $dsn = Dsn::fromString('wpdb://default');

        self::assertSame('wpdb', $dsn->getScheme());
        self::assertSame('default', $dsn->getHost());
    }

    // ── No-host URI ──

    #[Test]
    public function parseNoHostUri(): void
    {
        $dsn = Dsn::fromString('apcu://');

        self::assertSame('apcu', $dsn->getScheme());
        self::assertNull($dsn->getHost());
    }

    // ── Unix socket ──

    #[Test]
    public function parseUnixSocket(): void
    {
        $dsn = Dsn::fromString('redis:///var/run/redis.sock');

        self::assertSame('redis', $dsn->getScheme());
        self::assertNull($dsn->getHost());
        self::assertSame('/var/run/redis.sock', $dsn->getPath());
    }

    #[Test]
    public function parseUnixSocketWithQuery(): void
    {
        $dsn = Dsn::fromString('redis:///var/run/redis.sock?dbindex=2');

        self::assertSame('/var/run/redis.sock', $dsn->getPath());
        self::assertSame('2', $dsn->getOption('dbindex'));
    }

    // ── Array options ──

    #[Test]
    public function parseArrayOptions(): void
    {
        $dsn = Dsn::fromString('redis:?host[]=node1:6379&host[]=node2:6379');

        self::assertSame(['node1:6379', 'node2:6379'], $dsn->getArrayOption('host'));
    }

    #[Test]
    public function parseArrayOptionsWithKeyedBrackets(): void
    {
        $dsn = Dsn::fromString('redis:?host[node1:6379]&host[node2:6379]');

        self::assertSame(['node1:6379', 'node2:6379'], $dsn->getArrayOption('host'));
    }

    #[Test]
    public function getArrayOptionForScalarRetursSingleElementList(): void
    {
        $dsn = Dsn::fromString('redis://host:6379?dbindex=2');

        self::assertSame(['2'], $dsn->getArrayOption('dbindex'));
    }

    #[Test]
    public function getArrayOptionForMissingKeyReturnsEmpty(): void
    {
        $dsn = Dsn::fromString('redis://host:6379');

        self::assertSame([], $dsn->getArrayOption('missing'));
    }

    #[Test]
    public function getOptionReturnsNullForArrayValue(): void
    {
        $dsn = Dsn::fromString('redis:?host[]=a&host[]=b');

        self::assertNull($dsn->getOption('host'));
        self::assertSame('fallback', $dsn->getOption('host', 'fallback'));
    }

    // ── URL encoding ──

    #[Test]
    public function parseUrlEncodedCredentials(): void
    {
        $dsn = Dsn::fromString('mysql://user%40name:p%40ss%3Aword@host/db');

        self::assertSame('user@name', $dsn->getUser());
        self::assertSame('p@ss:word', $dsn->getPassword());
    }

    // ── getOptions ──

    #[Test]
    public function getOptionsReturnsAll(): void
    {
        $dsn = Dsn::fromString('mysql://host/db?charset=utf8mb4&timeout=5');

        self::assertSame(['charset' => 'utf8mb4', 'timeout' => '5'], $dsn->getOptions());
    }

    // ── No user/password ──

    #[Test]
    public function parseWithoutCredentials(): void
    {
        $dsn = Dsn::fromString('mysql://host:3306/dbname');

        self::assertNull($dsn->getUser());
        self::assertNull($dsn->getPassword());
    }

    #[Test]
    public function parseUserWithoutPassword(): void
    {
        $dsn = Dsn::fromString('mysql://user@host/db');

        self::assertSame('user', $dsn->getUser());
        self::assertNull($dsn->getPassword());
    }

    // ── No port ──

    #[Test]
    public function parseWithoutPort(): void
    {
        $dsn = Dsn::fromString('mysql://user:pass@host/dbname');

        self::assertNull($dsn->getPort());
    }

    // ── Error cases ──

    #[Test]
    public function throwsOnMissingScheme(): void
    {
        $this->expectException(InvalidDsnException::class);

        Dsn::fromString('host:3306/dbname');
    }

    #[Test]
    public function throwsOnInvalidFormat(): void
    {
        $this->expectException(InvalidDsnException::class);

        Dsn::fromString('mysql:host');
    }

    // ── Cache/Mailer/Storage compatibility ──

    #[Test]
    public function parseSesMailer(): void
    {
        $dsn = Dsn::fromString('ses+https://default');

        self::assertSame('ses+https', $dsn->getScheme());
        self::assertSame('default', $dsn->getHost());
    }

    #[Test]
    public function parseSmtpMailer(): void
    {
        $dsn = Dsn::fromString('smtp://user:pass@smtp.example.com:587');

        self::assertSame('smtp', $dsn->getScheme());
        self::assertSame('smtp.example.com', $dsn->getHost());
        self::assertSame(587, $dsn->getPort());
    }

    #[Test]
    public function parseS3Storage(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket?region=us-east-1');

        self::assertSame('s3', $dsn->getScheme());
        self::assertSame('my-bucket', $dsn->getHost());
        self::assertSame('us-east-1', $dsn->getOption('region'));
    }
}
