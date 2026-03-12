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

    #[Test]
    public function collectWithWordPressGathersFullData(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertNotEmpty($data['wp_version']);
        self::assertNotEmpty($data['theme']);
        self::assertIsArray($data['active_plugins']);
        self::assertIsArray($data['mu_plugins']);
        self::assertNotEmpty($data['environment_type']);
        self::assertIsBool($data['is_multisite']);
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
    public function getBadgeValueReturnsWpVersion(): void
    {
        global $wp_version;

        if (!isset($wp_version)) {
            self::markTestSkipped('$wp_version is not set.');
        }

        $this->collector->collect();
        self::assertSame($wp_version, $this->collector->getBadgeValue());
    }

    #[Test]
    public function collectGathersThemeInfoWithWordPress(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        $theme = wp_get_theme();
        self::assertSame($theme->get('Name'), $data['theme']);
        self::assertSame($theme->get('Version'), $data['theme_version']);
    }

    #[Test]
    public function collectWithWordPressGathersThemeAndPluginInfo(): void
    {
        if (!function_exists('wp_get_theme')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // Theme name should be a non-empty string
        self::assertIsString($data['theme']);
        self::assertNotEmpty($data['theme']);

        // Active plugins should be an array
        self::assertIsArray($data['active_plugins']);

        // MU plugins should be an array
        self::assertIsArray($data['mu_plugins']);

        // is_child_theme and is_block_theme should be booleans
        self::assertIsBool($data['is_child_theme']);
        self::assertIsBool($data['is_block_theme']);

        // child_theme and parent_theme are strings
        self::assertIsString($data['child_theme']);
        self::assertIsString($data['parent_theme']);

        // If not a child theme, child_theme and parent_theme should be empty
        if (!$data['is_child_theme']) {
            self::assertSame('', $data['child_theme']);
            self::assertSame('', $data['parent_theme']);
        }
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
    public function collectActivePluginsWithWordPress(): void
    {
        if (!function_exists('get_option') || !function_exists('get_plugin_data')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // active_plugins should be an associative array
        self::assertIsArray($data['active_plugins']);

        // Each entry should map plugin file => name
        foreach ($data['active_plugins'] as $file => $name) {
            self::assertIsString($file);
            self::assertIsString($name);
            self::assertNotEmpty($name);
        }
    }

    #[Test]
    public function collectMuPluginsWithWordPress(): void
    {
        if (!function_exists('get_mu_plugins')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        // mu_plugins should be an associative array (may be empty)
        self::assertIsArray($data['mu_plugins']);

        foreach ($data['mu_plugins'] as $file => $name) {
            self::assertIsString($file);
            self::assertIsString($name);
            self::assertNotEmpty($name);
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
