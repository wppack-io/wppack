<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Tests\Attribute\Filter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Shortcode\Attribute\Filter\PreDoShortcodeTagFilter;

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
