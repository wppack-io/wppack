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

namespace WpPack\Component\Hook\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;

final class FilterTest extends TestCase
{
    #[Test]
    public function createsWithHookNameAndDefaultPriority(): void
    {
        $filter = new Filter('the_content');

        self::assertSame('the_content', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function createsWithCustomPriority(): void
    {
        $filter = new Filter('the_content', priority: 20);

        self::assertSame(20, $filter->priority);
    }

    #[Test]
    public function extendsHook(): void
    {
        $filter = new Filter('the_content');

        self::assertInstanceOf(Hook::class, $filter);
    }

    #[Test]
    public function isUsableAsAttribute(): void
    {
        $reflection = new \ReflectionClass(Filter::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        self::assertSame(
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $attribute->flags,
        );
    }

    #[Test]
    public function isDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[Filter('the_title')]
            public function handle(string $title): string
            {
                return $title;
            }
        };

        $method = new \ReflectionMethod($class, 'handle');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertCount(1, $attributes);

        /** @var Hook $hook */
        $hook = $attributes[0]->newInstance();
        self::assertSame('the_title', $hook->hook);
        self::assertSame(HookType::Filter, $hook->type);
    }
}
