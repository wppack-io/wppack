<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Templating\Bridge\Twig\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use WPPack\Component\Templating\Bridge\Twig\TwigRenderer;
use WPPack\Component\Templating\Exception\RenderingException;
use WPPack\Component\Templating\Exception\TemplateNotFoundException;
use WPPack\Component\Templating\TemplateRendererInterface;

final class TwigRendererTest extends TestCase
{
    private TwigRenderer $renderer;

    protected function setUp(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/Fixtures/templates');
        $twig = new Environment($loader, [
            'strict_variables' => true,
            'autoescape' => 'html',
        ]);
        $this->renderer = new TwigRenderer($twig);
    }

    #[Test]
    public function implementsTemplateRendererInterface(): void
    {
        self::assertInstanceOf(TemplateRendererInterface::class, $this->renderer);
    }

    #[Test]
    public function rendersSimpleTemplate(): void
    {
        $html = $this->renderer->render('simple', ['title' => 'Hello World']);

        self::assertSame("<h1>Hello World</h1>\n", $html);
    }

    #[Test]
    public function rendersWithAutoResolvedExtension(): void
    {
        $html = $this->renderer->render('simple', ['title' => 'Auto']);

        self::assertStringContainsString('Auto', $html);
    }

    #[Test]
    public function rendersWithExplicitTwigExtension(): void
    {
        $html = $this->renderer->render('simple.html.twig', ['title' => 'Explicit']);

        self::assertStringContainsString('Explicit', $html);
    }

    #[Test]
    public function rendersWithDotTwigExtension(): void
    {
        $html = $this->renderer->render('simple.twig', ['title' => 'DotTwig']);

        self::assertStringContainsString('<h1>DotTwig</h1>', $html);
    }

    #[Test]
    public function throwsOnMissingTemplate(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        $this->renderer->render('nonexistent');
    }

    #[Test]
    public function wrapsRenderingErrors(): void
    {
        $this->expectException(RenderingException::class);

        // strict_variables is true, so accessing undefined variable throws
        $this->renderer->render('simple', []);
    }

    #[Test]
    public function existsReturnsTrueForExisting(): void
    {
        self::assertTrue($this->renderer->exists('simple'));
    }

    #[Test]
    public function existsReturnsFalseForMissing(): void
    {
        self::assertFalse($this->renderer->exists('nonexistent'));
    }

    #[Test]
    public function supportsReturnsTrueWhenExists(): void
    {
        self::assertTrue($this->renderer->supports('simple'));
    }

    #[Test]
    public function supportsReturnsFalseWhenMissing(): void
    {
        self::assertFalse($this->renderer->supports('nonexistent'));
    }

    #[Test]
    public function rendersTemplateWithLayout(): void
    {
        $html = $this->renderer->render('with-layout', ['title' => 'Page Title']);

        self::assertStringContainsString('<html><body>', $html);
        self::assertStringContainsString('<article>Page Title</article>', $html);
    }

    #[Test]
    public function rendersTemplateWithInclude(): void
    {
        $html = $this->renderer->render('partials/card', [
            'title' => 'Card Title',
            'body' => 'Card Body',
        ]);

        self::assertStringContainsString('<div class="card">', $html);
        self::assertStringContainsString('Card Title', $html);
        self::assertStringContainsString('Card Body', $html);
    }

    #[Test]
    public function autoEscapesHtml(): void
    {
        $html = $this->renderer->render('with-escaping', [
            'content' => '<script>alert("xss")</script>',
        ]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function getEnvironmentReturnsTwigEnvironment(): void
    {
        self::assertInstanceOf(Environment::class, $this->renderer->getEnvironment());
    }
}
