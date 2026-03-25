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
use WpPack\Component\Templating\ChainRenderer;
use WpPack\Component\Templating\Exception\TemplateNotFoundException;
use WpPack\Component\Templating\PhpRenderer;
use WpPack\Component\Templating\TemplateRendererInterface;

final class ChainRendererTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures/templates';
    }

    #[Test]
    public function implementsTemplateRendererInterface(): void
    {
        $chain = new ChainRenderer();

        self::assertInstanceOf(TemplateRendererInterface::class, $chain);
    }

    #[Test]
    public function delegatesToSupportingRenderer(): void
    {
        $phpRenderer = new PhpRenderer([$this->fixturesPath]);
        $chain = new ChainRenderer([$phpRenderer]);

        $result = $chain->render('simple', ['title' => 'Chain Test']);

        self::assertStringContainsString('Chain Test', $result);
    }

    #[Test]
    public function throwsWhenNoRendererSupports(): void
    {
        $chain = new ChainRenderer();

        $this->expectException(TemplateNotFoundException::class);

        $chain->render('nonexistent');
    }

    #[Test]
    public function existsReturnsTrueWhenAnyRendererHasTemplate(): void
    {
        $phpRenderer = new PhpRenderer([$this->fixturesPath]);
        $chain = new ChainRenderer([$phpRenderer]);

        self::assertTrue($chain->exists('simple'));
    }

    #[Test]
    public function existsReturnsFalseWhenNoRendererHasTemplate(): void
    {
        $phpRenderer = new PhpRenderer([$this->fixturesPath]);
        $chain = new ChainRenderer([$phpRenderer]);

        self::assertFalse($chain->exists('nonexistent'));
    }

    #[Test]
    public function supportsReturnsTrueWhenAnyRendererSupports(): void
    {
        $phpRenderer = new PhpRenderer([$this->fixturesPath]);
        $chain = new ChainRenderer([$phpRenderer]);

        self::assertTrue($chain->supports('simple'));
    }

    #[Test]
    public function supportsReturnsFalseWhenNoRendererSupports(): void
    {
        $chain = new ChainRenderer();

        self::assertFalse($chain->supports('nonexistent'));
    }

    #[Test]
    public function addRendererAppendsRenderer(): void
    {
        $chain = new ChainRenderer();

        self::assertFalse($chain->supports('simple'));

        $chain->addRenderer(new PhpRenderer([$this->fixturesPath]));

        self::assertTrue($chain->supports('simple'));
    }

    #[Test]
    public function delegatesToFirstSupportingRenderer(): void
    {
        $renderer1 = $this->createMock(TemplateRendererInterface::class);
        $renderer1->method('supports')->willReturn(false);
        $renderer1->expects(self::never())->method('render');

        $renderer2 = new PhpRenderer([$this->fixturesPath]);

        $chain = new ChainRenderer([$renderer1, $renderer2]);

        $result = $chain->render('simple', ['title' => 'Second']);

        self::assertStringContainsString('Second', $result);
    }

    #[Test]
    public function existsReturnsFalseWithEmptyRendererList(): void
    {
        $chain = new ChainRenderer();

        self::assertFalse($chain->exists('any-template'));
    }

    #[Test]
    public function existsReturnsFalseWhenMultipleRenderersDoNotHaveTemplate(): void
    {
        $renderer1 = $this->createMock(TemplateRendererInterface::class);
        $renderer1->method('exists')->willReturn(false);

        $renderer2 = $this->createMock(TemplateRendererInterface::class);
        $renderer2->method('exists')->willReturn(false);

        $chain = new ChainRenderer([$renderer1, $renderer2]);

        self::assertFalse($chain->exists('nonexistent'));
    }

    #[Test]
    public function supportsReturnsFalseWithEmptyRendererList(): void
    {
        $chain = new ChainRenderer();

        self::assertFalse($chain->supports('any-template'));
    }

    #[Test]
    public function renderThrowsWithEmptyRendererList(): void
    {
        $chain = new ChainRenderer();

        $this->expectException(TemplateNotFoundException::class);
        $this->expectExceptionMessage('Template "some-template" not found.');

        $chain->render('some-template');
    }

    #[Test]
    public function renderThrowsWhenMultipleRenderersDoNotSupport(): void
    {
        $renderer1 = $this->createMock(TemplateRendererInterface::class);
        $renderer1->method('supports')->willReturn(false);

        $renderer2 = $this->createMock(TemplateRendererInterface::class);
        $renderer2->method('supports')->willReturn(false);

        $chain = new ChainRenderer([$renderer1, $renderer2]);

        $this->expectException(TemplateNotFoundException::class);

        $chain->render('nonexistent');
    }

    #[Test]
    public function existsDelegatesToSecondRenderer(): void
    {
        $renderer1 = $this->createMock(TemplateRendererInterface::class);
        $renderer1->method('exists')->willReturn(false);

        $renderer2 = $this->createMock(TemplateRendererInterface::class);
        $renderer2->method('exists')->willReturn(true);

        $chain = new ChainRenderer([$renderer1, $renderer2]);

        self::assertTrue($chain->exists('some-template'));
    }

    #[Test]
    public function supportsDelegatesToSecondRenderer(): void
    {
        $renderer1 = $this->createMock(TemplateRendererInterface::class);
        $renderer1->method('supports')->willReturn(false);

        $renderer2 = $this->createMock(TemplateRendererInterface::class);
        $renderer2->method('supports')->willReturn(true);

        $chain = new ChainRenderer([$renderer1, $renderer2]);

        self::assertTrue($chain->supports('some-template'));
    }
}
