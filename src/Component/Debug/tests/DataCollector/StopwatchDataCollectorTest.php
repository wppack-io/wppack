<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\StopwatchDataCollector;
use WpPack\Component\Stopwatch\Stopwatch;

final class StopwatchDataCollectorTest extends TestCase
{
    private StopwatchDataCollector $collector;
    private Stopwatch $stopwatch;

    /** @var array<string, mixed> */
    private array $originalServer;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->stopwatch = new Stopwatch();
        $this->collector = new StopwatchDataCollector($this->stopwatch);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    #[Test]
    public function getNameReturnsStopwatch(): void
    {
        self::assertSame('stopwatch', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsStopwatch(): void
    {
        self::assertSame('Stopwatch', $this->collector->getLabel());
    }

    #[Test]
    public function collectGathersTotalTime(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('total_time', $data);
        self::assertArrayHasKey('request_time_float', $data);
        self::assertArrayHasKey('events', $data);
        self::assertArrayHasKey('phases', $data);

        self::assertIsFloat($data['total_time']);
        self::assertGreaterThanOrEqual(0.0, $data['total_time']);
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeValueReturnsEmpty(): void
    {
        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function frontendPhasesAreRecorded(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        $this->collector->onMuPluginsLoaded();
        $this->collector->onPluginsLoaded();
        $this->collector->onInit();
        $this->collector->onWpLoaded();
        $this->collector->onWp();
        $this->collector->onTemplateRedirect();
        $this->collector->onWpHead();
        $this->collector->onWpFooter();

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('wp', $data['phases']);
        self::assertArrayHasKey('wp_head', $data['phases']);
        self::assertArrayHasKey('wp_footer', $data['phases']);
    }

    #[Test]
    public function adminPhasesAreRecorded(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        $this->collector->onMuPluginsLoaded();
        $this->collector->onPluginsLoaded();
        $this->collector->onInit();
        $this->collector->onWpLoaded();
        $this->collector->onAdminInit();
        $this->collector->onAdminMenu();
        $this->collector->onAdminEnqueueScripts();
        $this->collector->onAdminHead();
        $this->collector->onAdminFooter();

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('admin_init', $data['phases']);
        self::assertArrayHasKey('admin_menu', $data['phases']);
        self::assertArrayHasKey('admin_enqueue_scripts', $data['phases']);
        self::assertArrayHasKey('admin_head', $data['phases']);
        self::assertArrayHasKey('admin_footer', $data['phases']);
    }

    #[Test]
    public function phasesAreOrderedChronologically(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);

        $this->collector->onMuPluginsLoaded();
        usleep(100);
        $this->collector->onPluginsLoaded();
        usleep(100);
        $this->collector->onInit();

        $this->collector->collect();
        $data = $this->collector->getData();

        $phases = $data['phases'];
        $values = array_values($phases);

        for ($i = 1; $i < count($values); $i++) {
            self::assertGreaterThanOrEqual($values[$i - 1], $values[$i], 'Phases should be in chronological order');
        }
    }
}
