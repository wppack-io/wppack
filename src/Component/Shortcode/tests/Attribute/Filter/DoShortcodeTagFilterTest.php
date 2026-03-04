<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Tests\Attribute\Filter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Shortcode\Attribute\Filter\DoShortcodeTagFilter;

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
