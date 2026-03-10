<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\WordPressDataCollector;

final class WordPressDataCollectorTest extends TestCase
{
    private WordPressDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new WordPressDataCollector();
    }

    #[Test]
    public function getNameReturnsWordpress(): void
    {
        self::assertSame('wordpress', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsWordPress(): void
    {
        self::assertSame('WordPress', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressFunctionsGathersPhpInfo(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        // PHP version is always available
        self::assertSame(PHP_VERSION, $data['php_version']);

        // Extensions are always available
        self::assertArrayHasKey('extensions', $data);
        self::assertIsArray($data['extensions']);
        self::assertNotEmpty($data['extensions']);

        // WordPress-specific data should be empty/default without WP
        self::assertArrayHasKey('wp_version', $data);
        self::assertArrayHasKey('theme', $data);
        self::assertArrayHasKey('active_plugins', $data);
        self::assertArrayHasKey('constants', $data);
    }

    #[Test]
    public function getBadgeValueReturnsVersionInfo(): void
    {
        $this->collector->collect();

        // Without WordPress, wp_version will be empty string
        $badgeValue = $this->collector->getBadgeValue();
        self::assertIsString($badgeValue);
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->collect();
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectIncludesThemeTypeFields(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('is_block_theme', $data);
        self::assertArrayHasKey('is_child_theme', $data);
        self::assertArrayHasKey('child_theme', $data);
        self::assertArrayHasKey('parent_theme', $data);
        self::assertArrayHasKey('theme_version', $data);
    }

    #[Test]
    public function collectReturnsDefaultThemeInfoWithoutWordPress(): void
    {
        if (function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertFalse($data['is_block_theme']);
        self::assertFalse($data['is_child_theme']);
        self::assertSame('', $data['child_theme']);
        self::assertSame('', $data['parent_theme']);
        self::assertSame('', $data['theme_version']);
    }

    #[Test]
    public function collectGathersDebugConstants(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('constants', $data);
        self::assertIsArray($data['constants']);

        // These constant keys should always be present
        self::assertArrayHasKey('WP_DEBUG', $data['constants']);
        self::assertArrayHasKey('SAVEQUERIES', $data['constants']);
        self::assertArrayHasKey('SCRIPT_DEBUG', $data['constants']);
    }

    #[Test]
    public function collectReturnsEmptyActivePluginsWithoutWordPress(): void
    {
        if (function_exists('get_option')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame([], $data['active_plugins']);
    }

    #[Test]
    public function collectReturnsEmptyThemeWithoutWordPress(): void
    {
        if (function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('', $data['theme']);
    }

    #[Test]
    public function collectReturnsFalseForMultisiteWithoutWordPress(): void
    {
        if (function_exists('is_multisite')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertFalse($data['is_multisite']);
    }
}
