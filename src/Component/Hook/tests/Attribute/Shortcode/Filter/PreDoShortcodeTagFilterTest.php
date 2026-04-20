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
use WPPack\Component\Hook\Attribute\Shortcode\Filter\PreDoShortcodeTagFilter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class PreDoShortcodeTagFilterTest extends TestCase
{
    #[Test]
    public function hasCorrectHookName(): void
    {
        $filter = new PreDoShortcodeTagFilter();

        self::assertSame('pre_do_shortcode_tag', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function acceptsCustomPriority(): void
    {
        $filter = new PreDoShortcodeTagFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function extendsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PreDoShortcodeTagFilter());
    }

    #[Test]
    public function isDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[PreDoShortcodeTagFilter]
            public function preFilterShortcodeTag(): void {}
        };

        $method = new \ReflectionMethod($class, 'preFilterShortcodeTag');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertCount(1, $attributes);
        self::assertSame('pre_do_shortcode_tag', $attributes[0]->newInstance()->hook);
    }
}
