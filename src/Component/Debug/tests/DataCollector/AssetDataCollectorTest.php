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
        $savedScripts = $GLOBALS['wp_scripts'] ?? null;
        $savedStyles = $GLOBALS['wp_styles'] ?? null;
        unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles']);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertSame([], $data['scripts']);
            self::assertSame([], $data['styles']);
            self::assertSame(0, $data['enqueued_scripts']);
            self::assertSame(0, $data['enqueued_styles']);
            self::assertSame(0, $data['registered_scripts']);
            self::assertSame(0, $data['registered_styles']);
        } finally {
            if ($savedScripts !== null) {
                $GLOBALS['wp_scripts'] = $savedScripts;
            }
            if ($savedStyles !== null) {
                $GLOBALS['wp_styles'] = $savedStyles;
            }
        }
    }

    #[Test]
    public function getIndicatorValueReturnsTotalEnqueued(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'enqueued_scripts' => 5,
            'enqueued_styles' => 3,
        ]);

        self::assertSame('8', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenZero(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'enqueued_scripts' => 0,
            'enqueued_styles' => 0,
        ]);

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
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

    #[Test]
    public function collectWithEnqueuedScriptsReturnsData(): void
    {
        if (!function_exists('wp_enqueue_script')) {
            self::markTestSkipped('WordPress asset functions are not available.');
        }

        wp_register_script('test-debug-script', '/js/test.js', ['jquery'], '1.0.0', true);
        wp_enqueue_script('test-debug-script');

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThanOrEqual(1, $data['enqueued_scripts']);
            self::assertGreaterThanOrEqual(1, $data['registered_scripts']);
            self::assertArrayHasKey('test-debug-script', $data['scripts']);
            self::assertSame('test-debug-script', $data['scripts']['test-debug-script']['handle']);
            self::assertSame('/js/test.js', $data['scripts']['test-debug-script']['src']);
            self::assertSame('1.0.0', $data['scripts']['test-debug-script']['version']);
            self::assertTrue($data['scripts']['test-debug-script']['in_footer']);
            self::assertTrue($data['scripts']['test-debug-script']['enqueued']);
            self::assertContains('jquery', $data['scripts']['test-debug-script']['deps']);
        } finally {
            wp_dequeue_script('test-debug-script');
            wp_deregister_script('test-debug-script');
        }
    }

    #[Test]
    public function collectWithEnqueuedStylesReturnsData(): void
    {
        if (!function_exists('wp_enqueue_style')) {
            self::markTestSkipped('WordPress asset functions are not available.');
        }

        wp_register_style('test-debug-style', '/css/test.css', [], '2.0.0', 'screen');
        wp_enqueue_style('test-debug-style');

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThanOrEqual(1, $data['enqueued_styles']);
            self::assertGreaterThanOrEqual(1, $data['registered_styles']);
            self::assertArrayHasKey('test-debug-style', $data['styles']);
            self::assertSame('test-debug-style', $data['styles']['test-debug-style']['handle']);
            self::assertSame('/css/test.css', $data['styles']['test-debug-style']['src']);
            self::assertSame('2.0.0', $data['styles']['test-debug-style']['version']);
            self::assertSame('screen', $data['styles']['test-debug-style']['media']);
            self::assertTrue($data['styles']['test-debug-style']['enqueued']);
        } finally {
            wp_dequeue_style('test-debug-style');
            wp_deregister_style('test-debug-style');
        }
    }

    #[Test]
    public function collectDistinguishesRegisteredFromEnqueued(): void
    {
        if (!function_exists('wp_register_script')) {
            self::markTestSkipped('WordPress asset functions are not available.');
        }

        wp_register_script('test-debug-registered-only', '/js/registered.js', [], '1.0.0', false);

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertArrayHasKey('test-debug-registered-only', $data['scripts']);
            self::assertFalse($data['scripts']['test-debug-registered-only']['enqueued']);
            self::assertFalse($data['scripts']['test-debug-registered-only']['in_footer']);
        } finally {
            wp_deregister_script('test-debug-registered-only');
        }
    }
}
