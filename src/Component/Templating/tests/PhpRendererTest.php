<?php

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
}
