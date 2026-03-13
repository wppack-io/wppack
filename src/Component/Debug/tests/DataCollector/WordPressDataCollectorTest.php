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
    public function collectWithoutWordPressFunctionsGathersBasicInfo(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        // WordPress-specific data should be empty/default without WP
        self::assertArrayHasKey('wp_version', $data);
        self::assertArrayHasKey('environment_type', $data);
        self::assertArrayHasKey('is_multisite', $data);
        self::assertArrayHasKey('constants', $data);
    }

    #[Test]
    public function getIndicatorValueReturnsVersionInfo(): void
    {
        $this->collector->collect();

        // Without WordPress, wp_version will be empty string
        $indicatorValue = $this->collector->getIndicatorValue();
        self::assertIsString($indicatorValue);
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
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
    public function collectReturnsFalseForMultisiteWithoutWordPress(): void
    {
        if (function_exists('is_multisite')) {
            self::markTestSkipped('WordPress functions are available; this test is for non-WP environments.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertFalse($data['is_multisite']);
    }

    #[Test]
    public function collectWithWordPressGathersFullData(): void
    {
        if (!function_exists('wp_get_environment_type')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertNotEmpty($data['wp_version']);
        self::assertNotEmpty($data['environment_type']);
        self::assertIsBool($data['is_multisite']);
        self::assertIsArray($data['constants']);
    }

    #[Test]
    public function collectIncludesWpVersionFromGlobal(): void
    {
        global $wp_version;

        if (!isset($wp_version)) {
            self::markTestSkipped('$wp_version is not set.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame($wp_version, $data['wp_version']);
    }

    #[Test]
    public function collectIncludesEnvironmentType(): void
    {
        if (!function_exists('wp_get_environment_type')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertNotEmpty($data['environment_type']);
        self::assertContains($data['environment_type'], ['local', 'development', 'staging', 'production']);
    }

    #[Test]
    public function getIndicatorValueReturnsWpVersion(): void
    {
        global $wp_version;

        if (!isset($wp_version)) {
            self::markTestSkipped('$wp_version is not set.');
        }

        $this->collector->collect();
        self::assertSame($wp_version, $this->collector->getIndicatorValue());
    }

    #[Test]
    public function collectWithWordPressGathersDebugConstants(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('constants', $data);
        $constants = $data['constants'];

        // All expected constant keys should be present
        $expectedKeys = ['WP_DEBUG', 'SAVEQUERIES', 'SCRIPT_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY', 'WP_CACHE'];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $constants, "Constant '$key' should be present");
        }

        // WP_DEBUG should be defined in the test environment
        if (defined('WP_DEBUG')) {
            self::assertSame(WP_DEBUG, $constants['WP_DEBUG']);
        } else {
            self::assertNull($constants['WP_DEBUG']);
        }

        // SAVEQUERIES should be defined or null
        if (defined('SAVEQUERIES')) {
            self::assertSame(SAVEQUERIES, $constants['SAVEQUERIES']);
        } else {
            self::assertNull($constants['SAVEQUERIES']);
        }

        // SCRIPT_DEBUG should be defined or null
        if (defined('SCRIPT_DEBUG')) {
            self::assertSame(SCRIPT_DEBUG, $constants['SCRIPT_DEBUG']);
        } else {
            self::assertNull($constants['SCRIPT_DEBUG']);
        }
    }

    #[Test]
    public function collectMultisiteStatusWithWordPress(): void
    {
        if (!function_exists('is_multisite')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // is_multisite should reflect the actual WP multisite status
        self::assertSame(is_multisite(), $data['is_multisite']);
    }
}
