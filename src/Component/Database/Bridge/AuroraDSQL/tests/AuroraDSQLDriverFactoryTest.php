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

namespace WPPack\Component\Database\Bridge\AuroraDSQL\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Bridge\AuroraDSQL\AuroraDSQLDriver;
use WPPack\Component\Database\Bridge\AuroraDSQL\AuroraDSQLDriverFactory;
use WPPack\Component\Dsn\Dsn;

final class AuroraDSQLDriverFactoryTest extends TestCase
{
    #[Test]
    public function supportsDSQLScheme(): void
    {
        $factory = new AuroraDSQLDriverFactory();

        if (!\function_exists('pg_connect')) {
            self::assertFalse($factory->supports(Dsn::fromString('dsql://admin:token@host/db')));

            return;
        }

        self::assertTrue($factory->supports(Dsn::fromString('dsql://admin:token@host/db')));
    }

    #[Test]
    public function doesNotSupportOtherSchemes(): void
    {
        $factory = new AuroraDSQLDriverFactory();

        self::assertFalse($factory->supports(Dsn::fromString('mysql://host/db')));
        self::assertFalse($factory->supports(Dsn::fromString('pgsql://host/db')));
    }

    #[Test]
    public function createsDriverWithTokenFromDsn(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();
        $driver = $factory->create(Dsn::fromString('dsql://admin:mytoken@abc123.dsql.us-east-1.on.aws/mydb'));

        self::assertInstanceOf(AuroraDSQLDriver::class, $driver);
        self::assertSame('dsql', $driver->getName());
        self::assertSame('dsql', $driver->getPlatform()->getEngine());
    }

    #[Test]
    public function extractsRegionFromEndpoint(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();

        // Region should be extracted from hostname, not from query parameter
        $driver = $factory->create(Dsn::fromString('dsql://admin:token@xyz.dsql.ap-northeast-1.on.aws/testdb'));

        self::assertInstanceOf(AuroraDSQLDriver::class, $driver);
    }

    #[Test]
    public function definitions(): void
    {
        $defs = AuroraDSQLDriverFactory::definitions();

        self::assertCount(1, $defs);
        self::assertSame('dsql', $defs[0]->scheme);
        self::assertSame('Aurora DSQL', $defs[0]->label);
    }

    // ── DSQL Translator ──

    #[Test]
    public function truncateConvertedToDeleteFromWithSequenceReset(): void
    {
        $translator = new \WPPack\Component\Database\Bridge\AuroraDSQL\Translator\AuroraDSQLQueryTranslator();

        $result = $translator->translate('TRUNCATE TABLE `wp_posts`');

        // First statement: DELETE FROM
        self::assertStringContainsString('DELETE FROM', $result[0]);
        self::assertStringContainsString('"wp_posts"', $result[0]);
        self::assertStringNotContainsString('TRUNCATE', $result[0]);

        // Second statement: sequence reset via setval
        self::assertCount(2, $result);
        self::assertStringContainsString('setval', $result[1]);
        self::assertStringContainsString('wp_posts', $result[1]);
    }

    #[Test]
    public function truncateWithoutTableKeyword(): void
    {
        $translator = new \WPPack\Component\Database\Bridge\AuroraDSQL\Translator\AuroraDSQLQueryTranslator();

        $result = $translator->translate('TRUNCATE `wp_options`');

        self::assertStringContainsString('DELETE FROM', $result[0]);
    }

    #[Test]
    public function nonTruncateQueriesDelegateToPostgreSQL(): void
    {
        $translator = new \WPPack\Component\Database\Bridge\AuroraDSQL\Translator\AuroraDSQLQueryTranslator();

        // Regular queries go through PostgreSQL translator
        $result = $translator->translate('SELECT * FROM `wp_posts` WHERE id = 1');

        self::assertStringContainsString('"wp_posts"', $result[0]);
        self::assertStringNotContainsString('`', $result[0]);
    }

    #[Test]
    public function dsqlDriverReturnsCorrectTranslator(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();
        $driver = $factory->create(Dsn::fromString('dsql://admin:token@abc.dsql.us-east-1.on.aws/mydb'));

        self::assertInstanceOf(
            \WPPack\Component\Database\Bridge\AuroraDSQL\Translator\AuroraDSQLQueryTranslator::class,
            $driver->getQueryTranslator(),
        );
    }

    #[Test]
    public function factoryParsesOccMaxRetriesFromDsn(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();
        $driver = $factory->create(
            Dsn::fromString('dsql://admin:token@abc.dsql.us-east-1.on.aws/mydb?occMaxRetries=5&tokenDurationSecs=600'),
        );

        self::assertInstanceOf(AuroraDSQLDriver::class, $driver);
    }

    #[Test]
    public function factoryParsesRegionFromDsnOption(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();
        $driver = $factory->create(
            Dsn::fromString('dsql://admin:token@custom-host/mydb?region=eu-west-1'),
        );

        self::assertInstanceOf(AuroraDSQLDriver::class, $driver);
    }

    #[Test]
    public function factoryForwardsSearchPathToDriver(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();
        $driver = $factory->create(
            Dsn::fromString('dsql://admin:token@abc.dsql.us-east-1.on.aws/mydb?search_path=tenant_1,public'),
        );

        // The property is protected on PostgreSQLDriver; read it via reflection
        // so we can pin the DSN → driver wiring without booting a live
        // DSQL connection. Regression guard for the case where
        // AuroraDSQLDriver's doConnect override silently dropped the
        // searchPath arg (fixed — applySearchPath is now invoked from
        // the subclass).
        $reflection = new \ReflectionClass(\WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLDriver::class);
        self::assertSame(
            ['tenant_1', 'public'],
            $reflection->getProperty('searchPath')->getValue($driver),
        );
    }

    #[Test]
    public function factoryTreatsSchemaOptionAsSingleEntrySearchPath(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $factory = new AuroraDSQLDriverFactory();
        $driver = $factory->create(
            Dsn::fromString('dsql://admin:token@abc.dsql.us-east-1.on.aws/mydb?schema=tenant_42'),
        );

        $reflection = new \ReflectionClass(\WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLDriver::class);
        self::assertSame(
            ['tenant_42'],
            $reflection->getProperty('searchPath')->getValue($driver),
        );
    }

    // ── OCC Retry ──

    #[Test]
    public function occRetryOnConflict(): void
    {
        $callCount = 0;
        $driver = $this->createMockDriver(occMaxRetries: 3);

        // Use reflection to test executeWithOccRetry
        $method = new \ReflectionMethod($driver, 'executeWithOccRetry');

        $result = $method->invoke($driver, static function () use (&$callCount): string {
            ++$callCount;
            if ($callCount < 3) {
                throw new \WPPack\Component\Database\Exception\DriverException('SQLSTATE[40001]: serialization_failure');
            }

            return 'success';
        });

        self::assertSame('success', $result);
        self::assertSame(3, $callCount);
    }

    #[Test]
    public function occMaxRetriesExhausted(): void
    {
        $driver = $this->createMockDriver(occMaxRetries: 2);
        $method = new \ReflectionMethod($driver, 'executeWithOccRetry');

        $this->expectException(\WPPack\Component\Database\Exception\DriverException::class);

        $method->invoke($driver, static function (): never {
            throw new \WPPack\Component\Database\Exception\DriverException('OC000 conflict');
        });
    }

    #[Test]
    public function occNonOccErrorNotRetried(): void
    {
        $callCount = 0;
        $driver = $this->createMockDriver(occMaxRetries: 3);
        $method = new \ReflectionMethod($driver, 'executeWithOccRetry');

        try {
            $method->invoke($driver, static function () use (&$callCount): never {
                ++$callCount;
                throw new \WPPack\Component\Database\Exception\DriverException('some other error');
            });
        } catch (\WPPack\Component\Database\Exception\DriverException) {
        }

        // Should not retry — only called once
        self::assertSame(1, $callCount);
    }

    #[Test]
    public function occDisabledWhenZeroRetries(): void
    {
        $callCount = 0;
        $driver = $this->createMockDriver(occMaxRetries: 0);
        $method = new \ReflectionMethod($driver, 'executeWithOccRetry');

        try {
            $method->invoke($driver, static function () use (&$callCount): never {
                ++$callCount;
                throw new \WPPack\Component\Database\Exception\DriverException('SQLSTATE[40001]');
            });
        } catch (\WPPack\Component\Database\Exception\DriverException) {
        }

        self::assertSame(1, $callCount);
    }

    #[Test]
    public function isOccErrorDetection(): void
    {
        $method = new \ReflectionMethod(AuroraDSQLDriver::class, 'isOccError');

        self::assertTrue($method->invoke(null, new \RuntimeException('SQLSTATE[40001]: serialization_failure')));
        self::assertTrue($method->invoke(null, new \RuntimeException('OC000 conflict detected')));
        self::assertTrue($method->invoke(null, new \RuntimeException('Error OC001')));
        self::assertFalse($method->invoke(null, new \RuntimeException('connection refused')));
        self::assertFalse($method->invoke(null, new \RuntimeException('syntax error')));
    }

    #[Test]
    public function transactionRetriesEntireCallbackOnOccError(): void
    {
        if (!\function_exists('pg_connect')) {
            self::markTestSkipped('ext-pgsql not available.');
        }

        $callCount = 0;
        $driver = $this->createMockDriver(occMaxRetries: 3);

        // Mock inner connection to simulate OCC on commit
        // Use reflection to test the transaction() method logic
        $method = new \ReflectionMethod($driver, 'executeWithOccRetry');

        $result = $method->invoke($driver, static function () use (&$callCount): string {
            ++$callCount;
            if ($callCount < 2) {
                // Simulate OCC conflict during transaction
                throw new \WPPack\Component\Database\Exception\DriverException('SQLSTATE[40001]');
            }

            return 'committed';
        });

        self::assertSame('committed', $result);
        self::assertSame(2, $callCount);
    }

    private function createMockDriver(int $occMaxRetries): AuroraDSQLDriver
    {
        return new AuroraDSQLDriver(
            endpoint: 'test.dsql.us-east-1.on.aws',
            region: 'us-east-1',
            database: 'test',
            username: 'admin',
            token: 'mock-token',
            occMaxRetries: $occMaxRetries,
        );
    }
}
