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

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\DatabaseDataCollector;

final class DatabaseDataCollectorTest extends TestCase
{
    private DatabaseDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new DatabaseDataCollector();
    }

    #[Test]
    public function getNameReturnsDatabase(): void
    {
        self::assertSame('database', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsDatabase(): void
    {
        self::assertSame('Database', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithNoWpdbReturnsEmptyQueries(): void
    {
        global $wpdb;
        $saved = $wpdb->queries ?? null;
        $wpdb->queries = [];

        try {
            // Fresh collector with no realtime queries and empty $wpdb->queries
            $collector = new DatabaseDataCollector();
            remove_filter('log_query_custom_data', [$collector, 'captureQueryData'], 10);

            $collector->collect();
            $data = $collector->getData();

            self::assertSame(0, $data['total_count']);
            self::assertSame(0.0, $data['total_time']);
            self::assertSame([], $data['queries']);
        } finally {
            if ($saved !== null) {
                $wpdb->queries = $saved;
            } else {
                unset($wpdb->queries);
            }
        }
    }

    #[Test]
    public function getIndicatorColorReturnsGreenForFastQueries(): void
    {
        global $wpdb;
        $saved = $wpdb->queries ?? null;
        $wpdb->queries = [];

        try {
            // Fresh collector with no queries (total_time = 0.0, which is < 0.5s)
            $collector = new DatabaseDataCollector();
            remove_filter('log_query_custom_data', [$collector, 'captureQueryData'], 10);

            $collector->collect();

            self::assertSame('green', $collector->getIndicatorColor());
        } finally {
            if ($saved !== null) {
                $wpdb->queries = $saved;
            } else {
                unset($wpdb->queries);
            }
        }
    }

    #[Test]
    public function getIndicatorColorReturnsYellowForModerateTime(): void
    {
        // Use reflection to set data directly to test threshold logic
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_time' => 600.0]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForSlowTime(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_time' => 1200.0]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorThresholdBoundaryAtHalfSecond(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 499.9ms should be green
        $reflection->setValue($this->collector, ['total_time' => 499.9]);
        self::assertSame('green', $this->collector->getIndicatorColor());

        // 500.0ms should be yellow
        $reflection->setValue($this->collector, ['total_time' => 500.0]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorThresholdBoundaryAtOneSecond(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 999.9ms should be yellow
        $reflection->setValue($this->collector, ['total_time' => 999.9]);
        self::assertSame('yellow', $this->collector->getIndicatorColor());

        // 1000.0ms should be red
        $reflection->setValue($this->collector, ['total_time' => 1000.0]);
        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorValueShowsTotalTime(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_time' => 1234.5]);

        self::assertSame('1.23 s', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function captureQueryDataCollectsRealtimeQueries(): void
    {
        $this->collector->captureQueryData([], 'SELECT 1', 0.001, 'TestCaller', 0.0);
        $this->collector->captureQueryData([], 'SELECT 2', 0.002, 'TestCaller', 0.001);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(2, $data['total_count']);
        self::assertGreaterThan(0.0, $data['total_time']);
    }

    #[Test]
    public function captureQueryDataPreservesSelectQueries(): void
    {
        $sql = 'SELECT * FROM wp_posts WHERE post_status = \'publish\' ORDER BY post_date DESC';
        $this->collector->captureQueryData([], $sql, 0.001, 'TestCaller', 0.0);

        $this->collector->collect();
        $data = $this->collector->getData();

        // SELECT queries without sensitive columns should be preserved
        self::assertStringContainsString('SELECT * FROM wp_posts', $data['queries'][0]['sql']);
    }

    #[Test]
    public function collectDetectsDuplicateQueries(): void
    {
        $sql = 'SELECT * FROM wp_options WHERE option_name = \'siteurl\'';

        // Capture the same SQL query twice
        $this->collector->captureQueryData([], $sql, 0.001, 'CallerA', 0.0);
        $this->collector->captureQueryData([], $sql, 0.002, 'CallerB', 0.001);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertGreaterThan(0, $data['duplicate_count']);
        self::assertSame(1, $data['duplicate_count']); // 2 identical queries => 1 duplicate
    }

    #[Test]
    public function collectDetectsSlowQueries(): void
    {
        // Capture a query with time > 0.1 seconds (100ms threshold)
        $this->collector->captureQueryData([], 'SELECT SLEEP(1)', 0.15, 'SlowCaller', 0.0);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertGreaterThan(0, $data['slow_count']);
        self::assertSame(1, $data['slow_count']);
    }

    #[Test]
    public function collectGeneratesSuggestions(): void
    {
        // Capture more than 50 queries to trigger the "high query count" suggestion
        for ($i = 0; $i < 51; $i++) {
            $this->collector->captureQueryData([], "SELECT * FROM wp_posts WHERE ID = {$i}", 0.001, 'BulkCaller', 0.0);
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertNotEmpty($data['suggestions']);
        self::assertSame(51, $data['total_count']);

        // Verify the high query count suggestion is present
        $found = false;
        foreach ($data['suggestions'] as $suggestion) {
            if (str_contains($suggestion, 'High query count')) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected a "High query count" suggestion');
    }

    #[Test]
    public function collectFallsBackToWpdbQueries(): void
    {

        global $wpdb;

        // Save original queries
        $originalQueries = $wpdb->queries ?? null;

        // Set mock wpdb->queries (format: [sql, time, caller, start, custom_data])
        $wpdb->queries = [
            ['SELECT 1 FROM wp_posts', 0.005, 'TestCaller::method', 0.0, []],
            ['SELECT 2 FROM wp_options', 0.003, 'TestCaller::other', 0.005, []],
        ];

        try {
            // Use a fresh collector without any realtime queries
            $freshCollector = new DatabaseDataCollector();
            // Remove the filter so it doesn't capture via realtime
            remove_filter('log_query_custom_data', [$freshCollector, 'captureQueryData'], 10);

            $freshCollector->collect();
            $data = $freshCollector->getData();

            self::assertSame(2, $data['total_count']);
            self::assertCount(2, $data['queries']);
        } finally {
            // Restore original queries
            if ($originalQueries !== null) {
                $wpdb->queries = $originalQueries;
            } else {
                unset($wpdb->queries);
            }
        }
    }

    #[Test]
    public function resetClearsRealtimeQueries(): void
    {
        global $wpdb;
        $saved = $wpdb->queries ?? null;
        $wpdb->queries = [];

        try {
            // Capture some data
            $this->collector->captureQueryData([], 'SELECT 1', 0.001, 'TestCaller', 0.0);
            $this->collector->captureQueryData([], 'SELECT 2', 0.002, 'TestCaller', 0.001);

            // Verify data exists before reset
            $this->collector->collect();
            $dataBefore = $this->collector->getData();
            self::assertSame(2, $dataBefore['total_count']);

            // Reset the collector
            $this->collector->reset();

            // Collect again — should have no queries (realtime cleared, $wpdb->queries empty)
            $this->collector->collect();
            $dataAfter = $this->collector->getData();

            self::assertSame(0, $dataAfter['total_count']);
            self::assertSame([], $dataAfter['queries']);
        } finally {
            if ($saved !== null) {
                $wpdb->queries = $saved;
            } else {
                unset($wpdb->queries);
            }
        }
    }
}
