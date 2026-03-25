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

namespace WpPack\Component\Hook\Tests\Attribute\Escaper;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Escaper\Filter\EscAttrFilter;
use WpPack\Component\Hook\Attribute\Escaper\Filter\EscHtmlFilter;
use WpPack\Component\Hook\Attribute\Escaper\Filter\EscJsFilter;
use WpPack\Component\Hook\Attribute\Escaper\Filter\EscUrlFilter;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function escAttrFilterHasCorrectHookName(): void
    {
        $filter = new EscAttrFilter();

        self::assertSame('esc_attr', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function escHtmlFilterHasCorrectHookName(): void
    {
        $filter = new EscHtmlFilter();

        self::assertSame('esc_html', $filter->hook);
    }

    #[Test]
    public function escJsFilterHasCorrectHookName(): void
    {
        $filter = new EscJsFilter();

        self::assertSame('esc_js', $filter->hook);
    }

    #[Test]
    public function escUrlFilterHasCorrectHookName(): void
    {
        $filter = new EscUrlFilter();

        self::assertSame('esc_url', $filter->hook);
    }

    #[Test]
    public function escHtmlFilterAcceptsCustomPriority(): void
    {
        $filter = new EscHtmlFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new EscAttrFilter());
        self::assertInstanceOf(Filter::class, new EscHtmlFilter());
        self::assertInstanceOf(Filter::class, new EscJsFilter());
        self::assertInstanceOf(Filter::class, new EscUrlFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[EscHtmlFilter(priority: 5)]
            public function onEscHtml(): void {}
        };

        $method = new \ReflectionMethod($class, 'onEscHtml');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('esc_html', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
