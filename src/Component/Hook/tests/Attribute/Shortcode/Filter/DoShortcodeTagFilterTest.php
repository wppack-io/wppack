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

namespace WPPack\Component\Hook\Tests\Attribute\Shortcode\Filter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;
use WPPack\Component\Hook\Attribute\Shortcode\Filter\DoShortcodeTagFilter;

final class DoShortcodeTagFilterTest extends TestCase
{
    #[Test]
    public function hasCorrectHookName(): void
    {
        $filter = new DoShortcodeTagFilter();

        self::assertSame('do_shortcode_tag', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function acceptsCustomPriority(): void
    {
        $filter = new DoShortcodeTagFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function extendsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new DoShortcodeTagFilter());
    }

    #[Test]
    public function isDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[DoShortcodeTagFilter]
            public function filterShortcodeTag(): void {}
        };

        $method = new \ReflectionMethod($class, 'filterShortcodeTag');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertCount(1, $attributes);
        self::assertSame('do_shortcode_tag', $attributes[0]->newInstance()->hook);
    }
}
