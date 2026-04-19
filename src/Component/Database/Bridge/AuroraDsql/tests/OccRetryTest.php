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

namespace WPPack\Component\Database\Bridge\AuroraDsql\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Exception\DriverException;

/**
 * Tests for Aurora DSQL's OCC retry loop — the exponential-backoff +
 * decorrelated-jitter helper that re-runs a transaction / statement
 * after SQLSTATE 40001 / OC000 / OC001 conflicts. Drive the private
 * executeWithOccRetry() via reflection on a slim subclass so we don't
 * need a live DSQL cluster.
 */
final class OccRetryTest extends TestCase
{
    private static function driverWithMaxRetries(int $maxRetries): object
    {
        // Slim harness that exposes the OCC retry method without going
        // through connection setup. AuroraDsqlDriver's constructor
        // requires async-aws/core wiring; we sidestep it via
        // ReflectionClass::newInstanceWithoutConstructor + property
        // injection.
        $class = new \ReflectionClass(\WPPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriver::class);
        $driver = $class->newInstanceWithoutConstructor();

        $class->getProperty('occMaxRetries')->setValue($driver, $maxRetries);
        // executeWithOccRetry reads $this->logger; initialise to null so
        // uninitialised-property access doesn't derail the test.
        $class->getProperty('logger')->setValue($driver, null);

        return $driver;
    }

    private static function invokeRetry(object $driver, \Closure $operation): mixed
    {
        $class = new \ReflectionClass(\WPPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriver::class);
        $method = $class->getMethod('executeWithOccRetry');

        return $method->invoke($driver, $operation);
    }

    #[Test]
    public function nonOccErrorsDoNotRetry(): void
    {
        $driver = self::driverWithMaxRetries(3);

        $attempts = 0;
        $caught = false;
        try {
            self::invokeRetry($driver, function () use (&$attempts): int {
                ++$attempts;
                throw new DriverException('syntax error — not a conflict', 42);
            });
        } catch (DriverException $e) {
            $caught = true;
            self::assertSame(42, $e->getCode());
        }

        self::assertTrue($caught);
        self::assertSame(1, $attempts, 'Non-OCC errors must NOT be retried.');
    }

    #[Test]
    public function occErrorRetriesExactlyOccMaxRetriesTimes(): void
    {
        $driver = self::driverWithMaxRetries(3);

        $attempts = 0;
        $caught = false;
        try {
            self::invokeRetry($driver, function () use (&$attempts): int {
                ++$attempts;
                throw new DriverException('ERROR: 40001 serialization failure');
            });
        } catch (DriverException) {
            $caught = true;
        }

        self::assertTrue($caught);
        // 1 initial call + 3 retries = 4 total invocations.
        self::assertSame(4, $attempts);
    }

    #[Test]
    public function occErrorStopsRetryingWhenOperationSucceeds(): void
    {
        $driver = self::driverWithMaxRetries(3);

        $attempts = 0;
        $result = self::invokeRetry($driver, function () use (&$attempts): string {
            ++$attempts;
            if ($attempts < 2) {
                throw new DriverException('40001 conflict');
            }

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(2, $attempts, 'Retry must stop as soon as the operation succeeds.');
    }

    #[Test]
    public function zeroRetriesPassesThroughWithoutRetry(): void
    {
        $driver = self::driverWithMaxRetries(0);

        $attempts = 0;
        $caught = false;
        try {
            self::invokeRetry($driver, function () use (&$attempts): int {
                ++$attempts;
                throw new DriverException('40001 conflict');
            });
        } catch (DriverException) {
            $caught = true;
        }

        self::assertTrue($caught);
        self::assertSame(1, $attempts, 'occMaxRetries=0 must not retry even on OCC errors.');
    }

    #[Test]
    public function ocSqlstatesAreRecognisedAsOccErrors(): void
    {
        $driver = self::driverWithMaxRetries(2);

        foreach (['OC000', 'OC001'] as $sqlstate) {
            $attempts = 0;
            try {
                self::invokeRetry($driver, function () use (&$attempts, $sqlstate): int {
                    ++$attempts;
                    throw new DriverException("DSQL-specific {$sqlstate} failure");
                });
            } catch (DriverException) {
                // expected
            }
            self::assertSame(3, $attempts, "SQLSTATE {$sqlstate} should trigger OCC retry (1 initial + 2 retries).");
        }
    }
}
