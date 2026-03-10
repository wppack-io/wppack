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
}
