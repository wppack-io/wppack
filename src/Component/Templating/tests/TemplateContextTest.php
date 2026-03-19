<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Templating\Exception\RenderingException;
use WpPack\Component\Templating\PhpRenderer;
use WpPack\Component\Templating\TemplateContext;

final class TemplateContextTest extends TestCase
{
    private TemplateContext $context;

    protected function setUp(): void
    {
        $escaper = new Escaper();
        $renderer = new PhpRenderer();
        $this->context = new TemplateContext($escaper, $renderer);
    }

    #[Test]
    public function eEscapesString(): void
    {
        $result = $this->context->e('<script>alert("XSS")</script>');

        self::assertStringNotContainsString('<script>', $result);
    }

    #[Test]
    public function eHandlesNull(): void
    {
        self::assertSame('', $this->context->e(null));
    }

    #[Test]
    public function eHandlesInteger(): void
    {
        $result = $this->context->e(42);

        self::assertSame('42', $result);
    }

    #[Test]
    public function eHandlesFloat(): void
    {
        $result = $this->context->e(3.14);

        self::assertSame('3.14', $result);
    }

    #[Test]
    public function eHandlesBoolTrue(): void
    {
        self::assertSame('1', $this->context->e(true));
    }

    #[Test]
    public function eHandlesBoolFalse(): void
    {
        self::assertSame('', $this->context->e(false));
    }

    #[Test]
    public function eHandlesStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return '<em>stringable</em>';
            }
        };

        $result = $this->context->e($stringable);

        self::assertStringNotContainsString('<em>', $result);
    }

    #[Test]
    public function eThrowsOnArray(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Cannot convert value of type "array"');

        $this->context->e(['not', 'stringable']);
    }

    #[Test]
    public function eThrowsOnNonStringableObject(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Cannot convert value of type');

        $this->context->e(new \stdClass());
    }

    #[Test]
    public function rawReturnsUnescapedString(): void
    {
        $html = '<b>bold</b>';

        self::assertSame($html, $this->context->raw($html));
    }

    #[Test]
    public function rawHandlesNull(): void
    {
        self::assertSame('', $this->context->raw(null));
    }

    #[Test]
    public function layoutSetsLayoutTemplate(): void
    {
        $this->context->layout('layouts/base');

        self::assertSame('layouts/base', $this->context->getLayoutTemplate());
    }

    #[Test]
    public function layoutSetsVariant(): void
    {
        $this->context->layout('layouts/base', 'wide');

        self::assertSame('wide', $this->context->getLayoutVariant());
    }

    #[Test]
    public function layoutIsNullByDefault(): void
    {
        self::assertNull($this->context->getLayoutTemplate());
    }

    #[Test]
    public function startAndStopCaptureSection(): void
    {
        $this->context->start('sidebar');
        echo 'Sidebar content';
        $this->context->stop();

        self::assertSame('Sidebar content', $this->context->section('sidebar'));
    }

    #[Test]
    public function sectionReturnsDefaultWhenNotDefined(): void
    {
        self::assertSame('default', $this->context->section('undefined', 'default'));
    }

    #[Test]
    public function sectionReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->context->section('undefined'));
    }

    #[Test]
    public function startThrowsOnNestedSection(): void
    {
        $this->context->start('outer');

        try {
            $this->context->start('inner');
            self::fail('Expected RenderingException was not thrown.');
        } catch (RenderingException $e) {
            self::assertStringContainsString('Cannot start section "inner": section "outer" is already open.', $e->getMessage());
        } finally {
            ob_end_clean();
        }
    }

    #[Test]
    public function stopThrowsWhenNoSectionOpen(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('no section is currently open');

        $this->context->stop();
    }

    #[Test]
    public function setSectionInjectsContent(): void
    {
        $this->context->setSection('content', '<p>injected</p>');

        self::assertSame('<p>injected</p>', $this->context->section('content'));
    }

    #[Test]
    public function rawHandlesInteger(): void
    {
        self::assertSame('42', $this->context->raw(42));
    }

    #[Test]
    public function rawHandlesFloat(): void
    {
        self::assertSame('3.14', $this->context->raw(3.14));
    }

    #[Test]
    public function rawHandlesBoolTrue(): void
    {
        self::assertSame('1', $this->context->raw(true));
    }

    #[Test]
    public function rawHandlesBoolFalse(): void
    {
        self::assertSame('', $this->context->raw(false));
    }

    #[Test]
    public function rawHandlesStringable(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return '<em>raw stringable</em>';
            }
        };

        self::assertSame('<em>raw stringable</em>', $this->context->raw($stringable));
    }

    #[Test]
    public function rawThrowsOnArray(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Cannot convert value of type "array"');

        $this->context->raw(['not', 'stringable']);
    }

    #[Test]
    public function rawThrowsOnNonStringableObject(): void
    {
        $this->expectException(RenderingException::class);
        $this->expectExceptionMessage('Cannot convert value of type');

        $this->context->raw(new \stdClass());
    }

    #[Test]
    public function resetLayoutClearsLayoutState(): void
    {
        $this->context->layout('layouts/base', 'wide');

        self::assertSame('layouts/base', $this->context->getLayoutTemplate());
        self::assertSame('wide', $this->context->getLayoutVariant());

        $this->context->resetLayout();

        self::assertNull($this->context->getLayoutTemplate());
        self::assertSame('', $this->context->getLayoutVariant());
    }

    #[Test]
    public function eWithDifferentStrategy(): void
    {
        $result = $this->context->e('value with <html>', 'attr');

        self::assertStringNotContainsString('<html>', $result);
    }

    #[Test]
    public function layoutVariantDefaultsToEmpty(): void
    {
        $this->context->layout('layouts/base');

        self::assertSame('', $this->context->getLayoutVariant());
    }
}
