<?php

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
        // Without WordPress ($wpdb is not available), collect should still work
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
        self::assertSame(0.0, $data['total_time']);
        self::assertSame([], $data['queries']);
    }

    #[Test]
    public function getBadgeColorReturnsGreenForLowQueryCount(): void
    {
        // Collect with no queries (total_count = 0, which is < 20)
        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsYellowForMediumQueryCount(): void
    {
        // Use reflection to set data directly to test threshold logic
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 25]);

        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsRedForHighQueryCount(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['total_count' => 55]);

        self::assertSame('red', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorThresholdBoundaryAt20(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 19 should be green
        $reflection->setValue($this->collector, ['total_count' => 19]);
        self::assertSame('green', $this->collector->getBadgeColor());

        // 20 should be yellow
        $reflection->setValue($this->collector, ['total_count' => 20]);
        self::assertSame('yellow', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorThresholdBoundaryAt50(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');

        // 49 should be yellow
        $reflection->setValue($this->collector, ['total_count' => 49]);
        self::assertSame('yellow', $this->collector->getBadgeColor());

        // 50 should be red
        $reflection->setValue($this->collector, ['total_count' => 50]);
        self::assertSame('red', $this->collector->getBadgeColor());
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
    public function captureQueryDataMasksInsertValues(): void
    {
        $sql = "INSERT INTO wp_users (user_login, user_pass) VALUES ('admin', 'hashed_pw')";
        $this->collector->captureQueryData([], $sql, 0.001, 'TestCaller', 0.0);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringContainsString('VALUES (********)', $data['queries'][0]['sql']);
        self::assertStringNotContainsString('hashed_pw', $data['queries'][0]['sql']);
    }

    #[Test]
    public function captureQueryDataMasksSensitiveColumnAssignments(): void
    {
        $sql = "UPDATE wp_users SET password = 'new_secret' WHERE user_id = 1";
        $this->collector->captureQueryData([], $sql, 0.001, 'TestCaller', 0.0);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringContainsString('password = ********', $data['queries'][0]['sql']);
        self::assertStringNotContainsString('new_secret', $data['queries'][0]['sql']);
    }

    #[Test]
    public function captureQueryDataMasksTokenColumnAssignment(): void
    {
        $sql = "UPDATE wp_users SET token = 'sk-abc123' WHERE user_id = 1";
        $this->collector->captureQueryData([], $sql, 0.001, 'TestCaller', 0.0);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringContainsString('token = ********', $data['queries'][0]['sql']);
        self::assertStringNotContainsString('sk-abc123', $data['queries'][0]['sql']);
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
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

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
    public function collectMasksMultipleSensitiveColumns(): void
    {
        $sensitiveColumns = ['api_key', 'apikey', 'secret', 'private_key', 'access_token', 'refresh_token', 'passwd', 'pwd'];

        foreach ($sensitiveColumns as $column) {
            $collector = new DatabaseDataCollector();
            $sql = "UPDATE wp_settings SET {$column} = 'sensitive_value_123' WHERE id = 1";
            $collector->captureQueryData([], $sql, 0.001, 'TestCaller', 0.0);

            $collector->collect();
            $data = $collector->getData();

            self::assertStringContainsString("{$column} = ********", $data['queries'][0]['sql'], "Column '{$column}' should be masked");
            self::assertStringNotContainsString('sensitive_value_123', $data['queries'][0]['sql'], "Sensitive value for '{$column}' should not be visible");
        }
    }

    #[Test]
    public function resetClearsRealtimeQueries(): void
    {
        // Capture some data
        $this->collector->captureQueryData([], 'SELECT 1', 0.001, 'TestCaller', 0.0);
        $this->collector->captureQueryData([], 'SELECT 2', 0.002, 'TestCaller', 0.001);

        // Verify data exists before reset
        $this->collector->collect();
        $dataBefore = $this->collector->getData();
        self::assertSame(2, $dataBefore['total_count']);

        // Reset the collector
        $this->collector->reset();

        // Collect again — should have no queries
        $this->collector->collect();
        $dataAfter = $this->collector->getData();

        self::assertSame(0, $dataAfter['total_count']);
        self::assertSame([], $dataAfter['queries']);
    }
}
