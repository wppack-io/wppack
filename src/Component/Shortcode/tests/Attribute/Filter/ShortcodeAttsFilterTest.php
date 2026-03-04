<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Tests\Attribute\Filter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Shortcode\Attribute\Filter\ShortcodeAttsFilter;

final class ShortcodeAttsFilterTest extends TestCase
{
    #[Test]
    public function hasCorrectDynamicHookName(): void
    {
        $filter = new ShortcodeAttsFilter(shortcode: 'gallery');

        self::assertSame('shortcode_atts_gallery', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function shortcodePropertyIsAccessible(): void
    {
        $filter = new ShortcodeAttsFilter(shortcode: 'button');

        self::assertSame('button', $filter->shortcode);
        self::assertSame('shortcode_atts_button', $filter->hook);
    }

    #[Test]
    public function acceptsCustomPriority(): void
    {
        $filter = new ShortcodeAttsFilter(shortcode: 'gallery', priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function extendsFilter(): void
    {
        self::assertInstanceOf(Filter::class, new ShortcodeAttsFilter(shortcode: 'test'));
    }

    #[Test]
    public function isDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[ShortcodeAttsFilter(shortcode: 'gallery')]
            public function filterGalleryAtts(): void {}
        };

        $method = new \ReflectionMethod($class, 'filterGalleryAtts');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertCount(1, $attributes);
        self::assertSame('shortcode_atts_gallery', $attributes[0]->newInstance()->hook);
    }
}
