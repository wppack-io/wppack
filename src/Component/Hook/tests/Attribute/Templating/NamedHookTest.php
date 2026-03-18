<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Templating;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Templating\Filter\TheContentFilter;
use WpPack\Component\Hook\Attribute\Templating\Filter\TheTitleFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function theContentFilterHasCorrectHookName(): void
    {
        $filter = new TheContentFilter();

        self::assertSame('the_content', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function theContentFilterAcceptsCustomPriority(): void
    {
        $filter = new TheContentFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function theTitleFilterHasCorrectHookName(): void
    {
        $filter = new TheTitleFilter();

        self::assertSame('the_title', $filter->hook);
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new TheContentFilter());
        self::assertInstanceOf(Filter::class, new TheTitleFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[TheContentFilter(priority: 5)]
            public function onContent(): void {}

            #[TheTitleFilter]
            public function onTitle(): void {}
        };

        $contentMethod = new \ReflectionMethod($class, 'onContent');
        $attributes = $contentMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('the_content', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);

        $titleMethod = new \ReflectionMethod($class, 'onTitle');
        $attributes = $titleMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('the_title', $attributes[0]->newInstance()->hook);
    }
}
