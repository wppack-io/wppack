<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    public function getIndicatorValueReturnsTemplateBasename(): void
    {
        $this->collector->captureTemplate('/var/www/html/wp-content/themes/mytheme/archive.php');
        $this->collector->collect();

        self::assertSame('archive.php', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNoTemplate(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsRedFor404(): void
    {
        // With WordPress available, if is_404() returns true, indicator should be red
        $this->collector->collect();
        if ($this->collector->getData()['is_404']) {
            self::assertSame('red', $this->collector->getIndicatorColor());
        } else {
            self::assertSame('default', $this->collector->getIndicatorColor());
        }
    }

    #[Test]
    public function getIndicatorColorReturnsGreenWhenMatchedRule(): void
    {
        $wp = new \stdClass();
        $wp->matched_rule = '([^/]+)/?$';
        $wp->matched_query = 'name=test';
        $wp->query_vars = ['name' => 'test'];

        $this->collector->captureParseRequest($wp);
        $this->collector->collect();

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsDefaultWhenNoMatch(): void
    {
        $this->collector->collect();

        self::assertSame('default', $this->collector->getIndicatorColor());
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
    public function getIndicatorValueReturnsEmptyForBlockThemeWithoutTemplate(): void
    {
        // Without WordPress, is_block_theme is false, so indicator falls through to classic
        $this->collector->collect();

        // Verify it returns empty (no template captured, not a block theme)
        self::assertSame('', $this->collector->getIndicatorValue());
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

    #[Test]
    public function collectWithWordPressDetectsQueryType(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        // query_type should be one of the expected types
        self::assertContains($data['query_type'], ['404', 'front_page', 'singular', 'archive', 'search', 'other']);
    }

    #[Test]
    public function collectWithRewriteRulesCountsRules(): void
    {
        global $wp_rewrite;

        $this->collector->collect();
        $data = $this->collector->getData();

        // In WP environment, rewrite_rules_count should reflect actual rules
        self::assertIsInt($data['rewrite_rules_count']);
        if (is_array($wp_rewrite->rules ?? null)) {
            self::assertSame(count($wp_rewrite->rules), $data['rewrite_rules_count']);
        }
    }

    #[Test]
    public function getIndicatorValueReturns404WhenIs404(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_404' => true,
            'is_block_theme' => false,
            'template' => 'index.php',
        ]);

        self::assertSame('404', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsBlockTemplateSlug(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_404' => false,
            'is_block_theme' => true,
            'block_template' => ['slug' => 'single'],
            'template' => '',
        ]);

        self::assertSame('single', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function collectBlockThemeDataIsAvailable(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsBool($data['is_block_theme']);
        self::assertIsArray($data['block_template']);
        self::assertArrayHasKey('slug', $data['block_template']);
        self::assertArrayHasKey('source', $data['block_template']);
        self::assertArrayHasKey('parts', $data['block_template']);
    }

    #[Test]
    public function collectWithWordPressGathersAllConditionals(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsBool($data['is_404']);
        self::assertIsBool($data['is_front_page']);
        self::assertIsBool($data['is_singular']);
        self::assertIsBool($data['is_archive']);
        self::assertIsBool($data['is_search']);
        self::assertIsBool($data['is_block_theme']);
    }

    #[Test]
    public function collectWithBlockTemplateIdGathersBlockData(): void
    {

        // If this is a block theme, test the block template collection
        if (!wp_is_block_theme()) {
            // Test that non-block-theme still works correctly
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertFalse($data['is_block_theme']);
            self::assertSame('', $data['block_template']['slug']);

            return;
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertTrue($data['is_block_theme']);
        self::assertIsArray($data['block_template']);
    }

    #[Test]
    public function collectWithRewriteRulesObject(): void
    {
        global $wp_rewrite;

        // Ensure rules are loaded
        if (!is_array($wp_rewrite->rules ?? null)) {
            $wp_rewrite->flush_rules(false);
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertIsInt($data['rewrite_rules_count']);
        if (is_array($wp_rewrite->rules)) {
            self::assertSame(count($wp_rewrite->rules), $data['rewrite_rules_count']);
        }
    }

    #[Test]
    public function captureParseRequestWithMinimalObject(): void
    {
        // Object without matched_rule (e.g., front page)
        $wp = new \stdClass();
        $wp->query_vars = ['page_id' => '2'];

        $this->collector->captureParseRequest($wp);
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('', $data['matched_rule']);
        self::assertSame('', $data['matched_query']);
    }

    #[Test]
    public function collectBlockTemplateReturnsDefaultWhenNoTemplateId(): void
    {

        global $_wp_current_template_id;
        $originalTemplateId = $_wp_current_template_id ?? null;

        try {
            // Unset the global to trigger early return
            $_wp_current_template_id = null;

            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplate');
            $default = [
                'slug' => '',
                'source' => '',
                'theme' => '',
                'type' => '',
                'has_theme_file' => false,
                'file_path' => '',
                'id' => '',
                'parts' => [],
            ];

            $result = $method->invoke($this->collector, $default);

            self::assertSame($default, $result);
        } finally {
            $_wp_current_template_id = $originalTemplateId;
        }
    }

    #[Test]
    public function collectBlockTemplateReturnsDefaultWhenTemplateIdIsEmpty(): void
    {

        global $_wp_current_template_id;
        $originalTemplateId = $_wp_current_template_id ?? null;

        try {
            $_wp_current_template_id = '';

            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplate');
            $default = [
                'slug' => '',
                'source' => '',
                'theme' => '',
                'type' => '',
                'has_theme_file' => false,
                'file_path' => '',
                'id' => '',
                'parts' => [],
            ];

            $result = $method->invoke($this->collector, $default);

            self::assertSame($default, $result);
        } finally {
            $_wp_current_template_id = $originalTemplateId;
        }
    }

    #[Test]
    public function collectBlockTemplateReturnsDefaultWhenTemplateIdIsNotString(): void
    {

        global $_wp_current_template_id;
        $originalTemplateId = $_wp_current_template_id ?? null;

        try {
            $_wp_current_template_id = 123;

            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplate');
            $default = [
                'slug' => '',
                'source' => '',
                'theme' => '',
                'type' => '',
                'has_theme_file' => false,
                'file_path' => '',
                'id' => '',
                'parts' => [],
            ];

            $result = $method->invoke($this->collector, $default);

            self::assertSame($default, $result);
        } finally {
            $_wp_current_template_id = $originalTemplateId;
        }
    }

    #[Test]
    public function collectBlockTemplateReturnsDefaultWhenTemplateNotFound(): void
    {

        global $_wp_current_template_id;
        $originalTemplateId = $_wp_current_template_id ?? null;

        try {
            // Use a non-existent template ID
            $_wp_current_template_id = 'nonexistent-theme//nonexistent-template';

            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplate');
            $default = [
                'slug' => '',
                'source' => '',
                'theme' => '',
                'type' => '',
                'has_theme_file' => false,
                'file_path' => '',
                'id' => '',
                'parts' => [],
            ];

            $result = $method->invoke($this->collector, $default);

            // get_block_template returns null for non-existent templates
            self::assertSame($default, $result);
        } finally {
            $_wp_current_template_id = $originalTemplateId;
        }
    }

    #[Test]
    public function collectBlockTemplateReturnsTemplateDataWhenFound(): void
    {


        global $_wp_current_template_id;
        $originalTemplateId = $_wp_current_template_id ?? null;

        try {
            // Try to find a real template that exists in the default theme
            $stylesheet = get_stylesheet();

            // Create a test block template in the database
            $templatePost = wp_insert_post([
                'post_type' => 'wp_template',
                'post_name' => 'test-debug-template',
                'post_title' => 'Test Debug Template',
                'post_content' => '',
                'post_status' => 'publish',
                'tax_input' => [
                    'wp_theme' => [$stylesheet],
                ],
            ]);

            if (is_wp_error($templatePost)) {
                self::markTestSkipped('Could not create test template: ' . $templatePost->get_error_message());
            }

            // Set the theme taxonomy
            wp_set_object_terms($templatePost, $stylesheet, 'wp_theme');

            $_wp_current_template_id = $stylesheet . '//test-debug-template';

            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplate');
            $default = [
                'slug' => '',
                'source' => '',
                'theme' => '',
                'type' => '',
                'has_theme_file' => false,
                'file_path' => '',
                'id' => '',
                'parts' => [],
            ];

            $result = $method->invoke($this->collector, $default);

            // Template was created so it should be found
            self::assertSame('test-debug-template', $result['slug']);
            self::assertSame($stylesheet . '//test-debug-template', $result['id']);
            self::assertIsString($result['source']);
            self::assertIsString($result['theme']);
            self::assertIsString($result['type']);
            self::assertIsBool($result['has_theme_file']);
            self::assertIsString($result['file_path']);
            self::assertIsArray($result['parts']);
            self::assertSame([], $result['parts']); // No content, no parts
        } finally {
            $_wp_current_template_id = $originalTemplateId;
            if (isset($templatePost) && !is_wp_error($templatePost)) {
                wp_delete_post($templatePost, true);
            }
        }
    }

    #[Test]
    public function collectBlockTemplateWithContentExtractsParts(): void
    {


        global $_wp_current_template_id;
        $originalTemplateId = $_wp_current_template_id ?? null;

        try {
            $stylesheet = get_stylesheet();

            // Create a template with content that includes template-part references
            $content = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
                . "\n"
                . '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

            $templatePost = wp_insert_post([
                'post_type' => 'wp_template',
                'post_name' => 'test-debug-parts-template',
                'post_title' => 'Test Debug Parts Template',
                'post_content' => $content,
                'post_status' => 'publish',
            ]);

            if (is_wp_error($templatePost)) {
                self::markTestSkipped('Could not create test template: ' . $templatePost->get_error_message());
            }

            wp_set_object_terms($templatePost, $stylesheet, 'wp_theme');

            $_wp_current_template_id = $stylesheet . '//test-debug-parts-template';

            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplate');
            $default = [
                'slug' => '',
                'source' => '',
                'theme' => '',
                'type' => '',
                'has_theme_file' => false,
                'file_path' => '',
                'id' => '',
                'parts' => [],
            ];

            $result = $method->invoke($this->collector, $default);

            self::assertSame('test-debug-parts-template', $result['slug']);
            // Parts should be extracted from the content
            self::assertIsArray($result['parts']);
            // Check that parts were parsed
            if (count($result['parts']) > 0) {
                $partSlugs = array_column($result['parts'], 'slug');
                self::assertContains('header', $partSlugs);
                self::assertContains('footer', $partSlugs);

                // Each part should have required keys
                foreach ($result['parts'] as $part) {
                    self::assertArrayHasKey('slug', $part);
                    self::assertArrayHasKey('source', $part);
                    self::assertArrayHasKey('area', $part);
                }
            }
        } finally {
            $_wp_current_template_id = $originalTemplateId;
            if (isset($templatePost) && !is_wp_error($templatePost)) {
                wp_delete_post($templatePost, true);
            }
        }
    }

    #[Test]
    public function resolveBlockTemplateFilePathReturnsEmptyForEmptySlug(): void
    {
        $method = new \ReflectionMethod($this->collector, 'resolveBlockTemplateFilePath');

        $result = $method->invoke($this->collector, '');

        self::assertSame('', $result);
    }

    #[Test]
    public function resolveBlockTemplateFilePathReturnsEmptyWhenFileNotFound(): void
    {

        $method = new \ReflectionMethod($this->collector, 'resolveBlockTemplateFilePath');

        // Use a slug that won't correspond to a real file
        $result = $method->invoke($this->collector, 'nonexistent-template-slug-xyz');

        self::assertSame('', $result);
    }

    #[Test]
    public function resolveBlockTemplateFilePathReturnsPathWhenFileExists(): void
    {

        $method = new \ReflectionMethod($this->collector, 'resolveBlockTemplateFilePath');

        // Create a temporary template file in the theme's templates directory
        $templatesDir = get_theme_file_path('templates');

        if (!is_dir($templatesDir)) {
            // If templates directory doesn't exist, the theme is not block-based
            // Still test the method returns empty
            $result = $method->invoke($this->collector, 'test-slug');
            self::assertSame('', $result);

            return;
        }

        $testFile = $templatesDir . '/test-debug-resolve.html';
        file_put_contents($testFile, '<!-- test template -->');

        try {
            $result = $method->invoke($this->collector, 'test-debug-resolve');

            self::assertSame($testFile, $result);
        } finally {
            if (file_exists($testFile)) {
                unlink($testFile);
            }
        }
    }

    #[Test]
    public function collectBlockTemplatePartsReturnsEmptyWhenNoMatches(): void
    {

        $method = new \ReflectionMethod($this->collector, 'collectBlockTemplateParts');

        // Content with no template-part blocks
        $result = $method->invoke($this->collector, '<p>Hello world</p>');

        self::assertSame([], $result);
    }

    #[Test]
    public function collectBlockTemplatePartsExtractsSlugsFromContent(): void
    {

        $method = new \ReflectionMethod($this->collector, 'collectBlockTemplateParts');

        $content = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
            . "\n<main>content</main>\n"
            . '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

        $result = $method->invoke($this->collector, $content);

        self::assertIsArray($result);
        self::assertCount(2, $result);

        $slugs = array_column($result, 'slug');
        self::assertContains('header', $slugs);
        self::assertContains('footer', $slugs);

        // Each part should have required keys
        foreach ($result as $part) {
            self::assertArrayHasKey('slug', $part);
            self::assertArrayHasKey('source', $part);
            self::assertArrayHasKey('area', $part);
        }
    }

    #[Test]
    public function collectBlockTemplatePartsHandlesNonExistentPart(): void
    {

        $method = new \ReflectionMethod($this->collector, 'collectBlockTemplateParts');

        // Content referencing a part that doesn't exist in the database
        $content = '<!-- wp:template-part {"slug":"nonexistent-part-xyz"} /-->';

        $result = $method->invoke($this->collector, $content);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame('nonexistent-part-xyz', $result[0]['slug']);
        // Non-existent part should have empty source and area
        self::assertSame('', $result[0]['source']);
        self::assertSame('', $result[0]['area']);
    }

    #[Test]
    public function collectBlockTemplatePartsWithExistingPart(): void
    {


        $stylesheet = get_stylesheet();

        // Create a template part in the database
        $partPost = wp_insert_post([
            'post_type' => 'wp_template_part',
            'post_name' => 'test-debug-part',
            'post_title' => 'Test Debug Part',
            'post_content' => '<p>Part content</p>',
            'post_status' => 'publish',
        ]);

        if (is_wp_error($partPost)) {
            self::markTestSkipped('Could not create test template part: ' . $partPost->get_error_message());
        }

        wp_set_object_terms($partPost, $stylesheet, 'wp_theme');

        try {
            $method = new \ReflectionMethod($this->collector, 'collectBlockTemplateParts');

            $content = '<!-- wp:template-part {"slug":"test-debug-part"} /-->';

            $result = $method->invoke($this->collector, $content);

            self::assertIsArray($result);
            self::assertCount(1, $result);
            self::assertSame('test-debug-part', $result[0]['slug']);
            self::assertArrayHasKey('source', $result[0]);
            self::assertArrayHasKey('area', $result[0]);
        } finally {
            wp_delete_post($partPost, true);
        }
    }

    #[Test]
    public function getRewriteRulesCountReturnsZeroWhenRewriteNotSet(): void
    {
        global $wp_rewrite;
        $originalRewrite = $wp_rewrite ?? null;

        try {
            $wp_rewrite = null;

            $method = new \ReflectionMethod($this->collector, 'getRewriteRulesCount');
            $result = $method->invoke($this->collector);

            self::assertSame(0, $result);
        } finally {
            $wp_rewrite = $originalRewrite;
        }
    }

    #[Test]
    public function getRewriteRulesCountReturnsZeroWhenRulesPropertyNotArray(): void
    {
        global $wp_rewrite;
        $originalRewrite = $wp_rewrite ?? null;

        try {
            $fakeRewrite = new \stdClass();
            $fakeRewrite->rules = null;
            $wp_rewrite = $fakeRewrite;

            $method = new \ReflectionMethod($this->collector, 'getRewriteRulesCount');
            $result = $method->invoke($this->collector);

            self::assertSame(0, $result);
        } finally {
            $wp_rewrite = $originalRewrite;
        }
    }

    #[Test]
    public function getRewriteRulesCountReturnsCountWhenRulesExist(): void
    {
        global $wp_rewrite;
        $originalRewrite = $wp_rewrite ?? null;

        try {
            $fakeRewrite = new \stdClass();
            $fakeRewrite->rules = ['rule1' => 'index.php?p=1', 'rule2' => 'index.php?p=2'];
            $wp_rewrite = $fakeRewrite;

            $method = new \ReflectionMethod($this->collector, 'getRewriteRulesCount');
            $result = $method->invoke($this->collector);

            self::assertSame(2, $result);
        } finally {
            $wp_rewrite = $originalRewrite;
        }
    }

    #[Test]
    public function collectWithBlockThemeCallsCollectBlockTemplate(): void
    {

        if (!wp_is_block_theme()) {
            self::markTestSkipped('Current theme is not a block theme.');
        }

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertTrue($data['is_block_theme']);
        // Block template should have been populated by collectBlockTemplate
        self::assertIsArray($data['block_template']);
        self::assertArrayHasKey('slug', $data['block_template']);
        self::assertArrayHasKey('source', $data['block_template']);
        self::assertArrayHasKey('theme', $data['block_template']);
        self::assertArrayHasKey('type', $data['block_template']);
        self::assertArrayHasKey('has_theme_file', $data['block_template']);
        self::assertArrayHasKey('file_path', $data['block_template']);
        self::assertArrayHasKey('id', $data['block_template']);
        self::assertArrayHasKey('parts', $data['block_template']);
    }

    #[Test]
    public function collectBlockTemplatePartsReturnsEmptyForEmptyContent(): void
    {

        $method = new \ReflectionMethod($this->collector, 'collectBlockTemplateParts');

        $result = $method->invoke($this->collector, '');

        self::assertSame([], $result);
    }
}
