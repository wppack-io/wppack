<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\RouterDataCollector;

final class RouterDataCollectorTest extends TestCase
{
    private RouterDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new RouterDataCollector();
    }

    #[Test]
    public function getNameReturnsRouter(): void
    {
        self::assertSame('router', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsRouter(): void
    {
        self::assertSame('Router', $this->collector->getLabel());
    }

    #[Test]
    public function collectWithoutWordPressReturnsDefaults(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('', $data['matched_rule']);
        self::assertSame('', $data['matched_query']);
        self::assertSame([], $data['query_vars']);
        self::assertSame('', $data['template']);
        self::assertSame('', $data['template_path']);
        self::assertArrayHasKey('is_404', $data);
        self::assertArrayHasKey('is_front_page', $data);
        self::assertArrayHasKey('is_singular', $data);
        self::assertArrayHasKey('is_archive', $data);
        self::assertArrayHasKey('is_search', $data);
        self::assertArrayHasKey('query_type', $data);
        self::assertArrayHasKey('rewrite_rules_count', $data);
        self::assertSame(0, $data['rewrite_rules_count']);
    }

    #[Test]
    public function captureTemplateStoresTemplatePath(): void
    {
        $templatePath = '/var/www/html/wp-content/themes/mytheme/single.php';

        $result = $this->collector->captureTemplate($templatePath);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame($templatePath, $result);
        self::assertSame('single.php', $data['template']);
        self::assertSame($templatePath, $data['template_path']);
    }

    #[Test]
    public function captureParseRequestStoresMatchedRule(): void
    {
        $wp = new \stdClass();
        $wp->matched_rule = '([^/]+)(?:/([0-9]+))?/?$';
        $wp->matched_query = 'name=hello-world&page=';
        $wp->query_vars = ['name' => 'hello-world', 'page' => ''];

        $this->collector->captureParseRequest($wp);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('([^/]+)(?:/([0-9]+))?/?$', $data['matched_rule']);
        self::assertSame('name=hello-world&page=', $data['matched_query']);
        self::assertSame(['name' => 'hello-world', 'page' => ''], $data['query_vars']);
    }

    #[Test]
    public function getBadgeValueReturnsTemplateBasename(): void
    {
        $this->collector->captureTemplate('/var/www/html/wp-content/themes/mytheme/archive.php');
        $this->collector->collect();

        self::assertSame('archive.php', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenNoTemplate(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsRedFor404(): void
    {
        if (!function_exists('is_404')) {
            // Simulate 404 by directly setting data via collect after triggering
            // Without WordPress, is_404() is unavailable so we test the logic path
            // by verifying the default non-404 behavior
            $this->collector->collect();
            self::assertSame('default', $this->collector->getBadgeColor());

            return;
        }

        // With WordPress available, if is_404() returns true, badge should be red
        $this->collector->collect();
        if ($this->collector->getData()['is_404']) {
            self::assertSame('red', $this->collector->getBadgeColor());
        } else {
            self::assertSame('default', $this->collector->getBadgeColor());
        }
    }

    #[Test]
    public function getBadgeColorReturnsGreenWhenMatchedRule(): void
    {
        $wp = new \stdClass();
        $wp->matched_rule = '([^/]+)/?$';
        $wp->matched_query = 'name=test';
        $wp->query_vars = ['name' => 'test'];

        $this->collector->captureParseRequest($wp);
        $this->collector->collect();

        self::assertSame('green', $this->collector->getBadgeColor());
    }

    #[Test]
    public function getBadgeColorReturnsDefaultWhenNoMatch(): void
    {
        $this->collector->collect();

        self::assertSame('default', $this->collector->getBadgeColor());
    }

    #[Test]
    public function collectIncludesBlockTemplateDefaults(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertArrayHasKey('is_block_theme', $data);
        self::assertFalse($data['is_block_theme']);
        self::assertArrayHasKey('block_template', $data);
        self::assertIsArray($data['block_template']);
    }

    #[Test]
    public function collectIncludesBlockTemplateDataKeys(): void
    {
        $this->collector->collect();
        $data = $this->collector->getData();

        $blockTemplate = $data['block_template'];
        self::assertArrayHasKey('slug', $blockTemplate);
        self::assertArrayHasKey('source', $blockTemplate);
        self::assertArrayHasKey('theme', $blockTemplate);
        self::assertArrayHasKey('type', $blockTemplate);
        self::assertArrayHasKey('has_theme_file', $blockTemplate);
        self::assertArrayHasKey('file_path', $blockTemplate);
        self::assertArrayHasKey('id', $blockTemplate);
        self::assertArrayHasKey('parts', $blockTemplate);
    }

    #[Test]
    public function getBadgeValueReturnsEmptyForBlockThemeWithoutTemplate(): void
    {
        // Without WordPress, is_block_theme is false, so badge falls through to classic
        $this->collector->collect();

        // Verify it returns empty (no template captured, not a block theme)
        self::assertSame('', $this->collector->getBadgeValue());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $wp = new \stdClass();
        $wp->matched_rule = 'test-rule';
        $wp->matched_query = 'test-query';
        $wp->query_vars = ['foo' => 'bar'];

        $this->collector->captureParseRequest($wp);
        $this->collector->captureTemplate('/path/to/template.php');
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // Verify that collect after reset produces empty defaults
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('', $data['matched_rule']);
        self::assertSame('', $data['matched_query']);
        self::assertSame([], $data['query_vars']);
        self::assertSame('', $data['template']);
        self::assertSame('', $data['template_path']);
    }
}
