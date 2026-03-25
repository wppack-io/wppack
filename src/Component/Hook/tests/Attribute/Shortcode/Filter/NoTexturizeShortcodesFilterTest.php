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

namespace WpPack\Component\Hook\Tests\Attribute\Shortcode\Filter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Shortcode\Filter\NoTexturizeShortcodesFilter;

final class NoTexturizeShortcodesFilterTest extends TestCase
{
    #[Test]
    public function hasCorrectHookName(): void
    {
        $filter = new NoTexturizeShortcodesFilter();

        self::assertSame('no_texturize_shortcodes', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function acceptsCustomPriority(): void
    {
        $filter = new NoTexturizeShortcodesFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function extendsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new NoTexturizeShortcodesFilter());
    }

    #[Test]
    public function isDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[NoTexturizeShortcodesFilter]
            public function filterNoTexturizeShortcodes(): void {}
        };

        $method = new \ReflectionMethod($class, 'filterNoTexturizeShortcodes');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertCount(1, $attributes);
        self::assertSame('no_texturize_shortcodes', $attributes[0]->newInstance()->hook);
    }
}
