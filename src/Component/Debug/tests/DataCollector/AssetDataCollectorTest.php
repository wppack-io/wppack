<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\AssetDataCollector;

final class AssetDataCollectorTest extends TestCase
{
    private AssetDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AssetDataCollector();
    }

    #[Test]
    public function getNameReturnsAsset(): void
    {
        self::assertSame('asset', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsAssets(): void
    {
        self::assertSame('Assets', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutGlobalsReturnsDefaults(): void
    {
        // Ensure globals are not set
        unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles']);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['scripts']);
        self::assertSame([], $data['styles']);
        self::assertSame(0, $data['enqueued_scripts']);
        self::assertSame(0, $data['enqueued_styles']);
        self::assertSame(0, $data['registered_scripts']);
        self::assertSame(0, $data['registered_styles']);
    }

    #[Test]
    public function getBadgeValueReturnsTotalEnqueued(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'enqueued_scripts' => 5,
            'enqueued_styles' => 3,
        ]);

        self::assertSame('8', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'enqueued_scripts' => 0,
            'enqueued_styles' => 0,
        ]);

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['enqueued_scripts' => 5]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
