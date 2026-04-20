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

namespace WPPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\QueryLoggerInterface;
use WPPack\Component\Database\WpSaveQueriesLogger;

#[CoversClass(WpSaveQueriesLogger::class)]
final class WpSaveQueriesLoggerTest extends TestCase
{
    private function fakeWpdb(): \wpdb
    {
        /** @var \wpdb $wpdb */
        $wpdb = (new \ReflectionClass(\wpdb::class))->newInstanceWithoutConstructor();
        $wpdb->queries = [];

        return $wpdb;
    }

    #[Test]
    public function implementsQueryLoggerInterface(): void
    {
        self::assertInstanceOf(QueryLoggerInterface::class, new WpSaveQueriesLogger($this->fakeWpdb()));
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function appendsQueryToWpdbQueriesWhenSaveQueriesEnabled(): void
    {
        \define('SAVEQUERIES', true);

        $wpdb = $this->fakeWpdb();
        $logger = new WpSaveQueriesLogger($wpdb);

        $logger->log('SELECT 1', [], 12.5);

        self::assertCount(1, $wpdb->queries);
        [$sql, $elapsed, $caller] = $wpdb->queries[0];
        self::assertSame('SELECT 1', $sql);
        self::assertEqualsWithDelta(0.0125, $elapsed, 0.0001);
        self::assertSame('', $caller);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function noOpWhenSaveQueriesDisabled(): void
    {
        \define('SAVEQUERIES', false);

        $wpdb = $this->fakeWpdb();
        $logger = new WpSaveQueriesLogger($wpdb);

        $logger->log('SELECT 1', [], 12.5);

        self::assertSame([], $wpdb->queries);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function noOpWhenSaveQueriesUndefined(): void
    {
        // In a fresh process, SAVEQUERIES is not defined.
        $wpdb = $this->fakeWpdb();
        $logger = new WpSaveQueriesLogger($wpdb);

        $logger->log('SELECT 1', [], 12.5);

        self::assertSame([], $wpdb->queries);
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function convertsMillisecondsToSeconds(): void
    {
        \define('SAVEQUERIES', true);

        $wpdb = $this->fakeWpdb();
        $logger = new WpSaveQueriesLogger($wpdb);

        $logger->log('SELECT 2', [], 1500.0);

        self::assertEqualsWithDelta(1.5, $wpdb->queries[0][1], 0.0001);
    }
}
