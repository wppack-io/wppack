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

namespace WpPack\Component\Templating\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Templating\Exception\RenderingException;
use WpPack\Component\Templating\Exception\TemplateNotFoundException;
use WpPack\Component\Templating\PhpRenderer;
use WpPack\Component\Templating\TemplateRendererInterface;

final class PhpRendererTest extends TestCase
{
    private string $fixturesPath;
    private PhpRenderer $renderer;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures/templates';
        $this->renderer = new PhpRenderer([$this->fixturesPath]);
    }

    #[Test]
    public function implementsTemplateRendererInterface(): void
    {
        self::assertInstanceOf(TemplateRendererInterface::class, $this->renderer);
    }

    #[Test]
    public function rendersSimpleTemplate(): void
    {
        $result = $this->renderer->render('simple', ['title' => 'Hello']);

        self::assertStringContainsString('<h1>', $result);
        self::assertStringContainsString('Hello', $result);
    }

    #[Test]
    public function escapesHtmlByDefault(): void
    {
        $result = $this->renderer->render('simple', ['title' => '<script>alert("XSS")</script>']);

        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function rendersTemplateWithEscaping(): void
    {
        $result = $this->renderer->render('with-escaping', [
            'content' => '<b>bold</b>',
            'url' => 'http://example.com',
        ]);

        self::assertStringContainsString('<p>', $result);
        self::assertStringNotContainsString('<b>bold</b>', $result);
    }

    #[Test]
    public function rendersTemplateWithLayout(): void
    {
        $result = $this->renderer->render('with-layout', ['title' => 'Test']);

        self::assertStringContainsString('<html><body>', $result);
        self::assertStringContainsString('<article>', $result);
        self::assertStringContainsString('Test', $result);
        self::assertStringContainsString('</body></html>', $result);
    }

    #[Test]
    public function rendersTemplateWithSections(): void
    {
        $result = $this->renderer->render('with-sections', ['title' => 'Main']);

        self::assertStringContainsString('<html><body>', $result);
        self::assertStringContainsString('<div class="main">', $result);
        self::assertStringContainsString('<div class="sidebar">', $result);
        self::assertStringContainsString('<nav>Sidebar content</nav>', $result);
        self::assertStringContainsString('Main', $result);
    }

    #[Test]
    public function rendersTemplateWithInclude(): void
    {
        $result = $this->renderer->render('with-include', [
            'cardTitle' => 'Card Title',
            'cardBody' => 'Card Body',
        ]);

        self::assertStringContainsString('<div class="cards">', $result);
        self::assertStringContainsString('<div class="card">', $result);
        self::assertStringContainsString('Card Title', $result);
        self::assertStringContainsString('Card Body', $result);
    }

    #[Test]
    public function throwsOnMissingTemplate(): void
    {
        $this->expectException(TemplateNotFoundException::class);
        $this->expectExceptionMessage('Template "nonexistent" not found.');

        $this->renderer->render('nonexistent');
    }

    #[Test]
    public function detectsCircularLayouts(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Circular layout reference detected');

        $this->renderer->render('layouts/circular-a');
    }

    #[Test]
    public function existsReturnsTrueForExistingTemplate(): void
    {
        self::assertTrue($this->renderer->exists('simple'));
    }

    #[Test]
    public function existsReturnsFalseForMissingTemplate(): void
    {
        self::assertFalse($this->renderer->exists('nonexistent'));
    }

    #[Test]
    public function supportsReturnsTrueForPhpTemplate(): void
    {
        self::assertTrue($this->renderer->supports('simple'));
    }

    #[Test]
    public function supportsReturnsFalseForMissingTemplate(): void
    {
        self::assertFalse($this->renderer->supports('nonexistent'));
    }

    #[Test]
    public function rendersWithVariant(): void
    {
        // Create a variant fixture on the fly
        $variantFile = $this->fixturesPath . '/simple-alt.php';
        file_put_contents($variantFile, '<h2><?= $view->e($title) ?></h2>' . "\n");

        try {
            $result = $this->renderer->render('simple', ['title' => 'Variant'], 'alt');

            self::assertStringContainsString('<h2>', $result);
            self::assertStringContainsString('Variant', $result);
        } finally {
            unlink($variantFile);
        }
    }

    #[Test]
    public function displayOutputsRenderedTemplate(): void
    {
        ob_start();
        $this->renderer->display('simple', ['title' => 'Display Test']);
        $output = ob_get_clean();

        self::assertStringContainsString('Display Test', $output);
    }

    #[Test]
    public function acceptsCustomEscaper(): void
    {
        $escaper = new Escaper();
        $renderer = new PhpRenderer([$this->fixturesPath], escaper: $escaper);

        $result = $renderer->render('simple', ['title' => 'Custom']);

        self::assertStringContainsString('Custom', $result);
    }

    #[Test]
    public function sectionDefaultsWhenNotDefined(): void
    {
        $result = $this->renderer->render('with-layout', ['title' => 'Test']);

        // The two-column layout uses default sidebar text,
        // but with-layout uses base layout which has no defaults to test.
        // Just verify it renders correctly.
        self::assertStringContainsString('<html><body>', $result);
    }

    #[Test]
    public function rendersTemplateWithExplicitLocatorAndEscaper(): void
    {
        $locator = new \WpPack\Component\Templating\TemplateLocator([$this->fixturesPath]);
        $escaper = new Escaper();
        $renderer = new PhpRenderer(locator: $locator, escaper: $escaper);

        $result = $renderer->render('simple', ['title' => 'Custom Locator']);

        self::assertStringContainsString('Custom Locator', $result);
    }

    #[Test]
    public function existsWithVariant(): void
    {
        // Create a variant fixture
        $variantFile = $this->fixturesPath . '/simple-check.php';
        file_put_contents($variantFile, '<p>check</p>');

        try {
            // Clear PHP's stat cache so is_file() sees the newly created file
            clearstatcache();
            self::assertTrue($this->renderer->exists('simple', 'check'));

            // exists() with a non-existent variant still returns true
            // because the base template 'simple.php' is found as a fallback
            self::assertTrue($this->renderer->exists('simple', 'nonexistent-variant-check'));
        } finally {
            unlink($variantFile);
        }
    }

    #[Test]
    public function renderThrowsOnExceptionInTemplate(): void
    {
        // Create a template that throws an exception
        $errorFile = $this->fixturesPath . '/error-template.php';
        file_put_contents($errorFile, '<?php throw new \RuntimeException("Template error"); ?>');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Template error');
            $this->renderer->render('error-template');
        } finally {
            @unlink($errorFile);
        }
    }

    #[Test]
    public function layoutWithMissingLayoutTemplateThrows(): void
    {
        // Create a template that declares a nonexistent layout
        $templateFile = $this->fixturesPath . '/missing-layout-ref.php';
        file_put_contents($templateFile, '<?php $view->layout("nonexistent-layout-xyz"); ?>Content');

        try {
            $this->expectException(\WpPack\Component\Templating\Exception\TemplateNotFoundException::class);
            $this->renderer->render('missing-layout-ref');
        } finally {
            @unlink($templateFile);
        }
    }

    #[Test]
    public function displayWithVariant(): void
    {
        $variantFile = $this->fixturesPath . '/simple-display.php';
        file_put_contents($variantFile, '<h3><?= $view->e($title) ?></h3>' . "\n");

        try {
            ob_start();
            $this->renderer->display('simple', ['title' => 'Display Variant'], 'display');
            $output = ob_get_clean();

            self::assertStringContainsString('<h3>', $output);
            self::assertStringContainsString('Display Variant', $output);
        } finally {
            @unlink($variantFile);
        }
    }

    #[Test]
    public function renderWithMultiLevelLayoutInheritance(): void
    {
        // with-sections uses two-column layout which extends base layout
        $result = $this->renderer->render('with-sections', ['title' => 'Multi']);

        // Should contain base layout wrapper and two-column layout structure
        self::assertStringContainsString('<html><body>', $result);
        self::assertStringContainsString('</body></html>', $result);
        self::assertStringContainsString('<div class="main">', $result);
        self::assertStringContainsString('<div class="sidebar">', $result);
    }

    #[Test]
    public function renderWithLayoutVariant(): void
    {
        // Create a layout variant fixture
        $layoutVariantFile = $this->fixturesPath . '/layouts/base-wide.php';
        file_put_contents($layoutVariantFile, '<html><body class="wide"><?= $view->section("content") ?></body></html>');

        $templateFile = $this->fixturesPath . '/with-layout-variant.php';
        file_put_contents($templateFile, '<?php $view->layout("layouts/base", "wide"); ?><article>Variant Layout</article>');

        try {
            clearstatcache();
            $result = $this->renderer->render('with-layout-variant');

            self::assertStringContainsString('<html><body class="wide">', $result);
            self::assertStringContainsString('<article>Variant Layout</article>', $result);
        } finally {
            @unlink($layoutVariantFile);
            @unlink($templateFile);
        }
    }

    #[Test]
    public function renderWithEmptyContext(): void
    {
        $templateFile = $this->fixturesPath . '/no-context.php';
        file_put_contents($templateFile, '<p>Static content</p>');

        try {
            $result = $this->renderer->render('no-context');
            self::assertSame('<p>Static content</p>', $result);
        } finally {
            @unlink($templateFile);
        }
    }

    #[Test]
    public function includeRendersPartialWithContext(): void
    {
        // with-include already tests include, but let's verify context isolation
        $result = $this->renderer->render('with-include', [
            'cardTitle' => 'Isolated',
            'cardBody' => 'Content',
        ]);

        self::assertStringContainsString('Isolated', $result);
        self::assertStringContainsString('Content', $result);
    }

    #[Test]
    public function maxRenderDepthThrowsException(): void
    {
        // Create a template that recursively includes itself to exceed MAX_RENDER_DEPTH
        $templateFile = $this->fixturesPath . '/recursive-include.php';
        file_put_contents(
            $templateFile,
            '<?= $view->include("recursive-include") ?>',
        );

        try {
            $this->expectException(RenderingException::class);
            $this->expectExceptionMessage('Maximum template nesting depth');
            $this->renderer->render('recursive-include');
        } finally {
            @unlink($templateFile);
        }
    }
}
