<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\AjaxDataCollector;

final class AjaxDataCollectorTest extends TestCase
{
    private AjaxDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AjaxDataCollector();
    }

    #[Test]
    public function getNameReturnsAjax(): void
    {
        self::assertSame('ajax', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsAjax(): void
    {
        self::assertSame('Ajax', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutGlobalsReturnsDefaults(): void
    {
        $saved = $GLOBALS['wp_filter'] ?? null;
        unset($GLOBALS['wp_filter']);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertSame([], $data['registered_actions']);
            self::assertSame(0, $data['total_actions']);
            self::assertSame(0, $data['nopriv_count']);
        } finally {
            if ($saved !== null) {
                $GLOBALS['wp_filter'] = $saved;
            }
        }
    }

    #[Test]
    public function getBadgeValueReturnsZero(): void
    {
        self::assertSame('0', $this->collector->getBadgeValue());
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
        $reflection->setValue($this->collector, ['total_actions' => 5]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }
}
