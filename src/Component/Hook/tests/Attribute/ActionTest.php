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
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;

final class ActionTest extends TestCase
{
    #[Test]
    public function createsWithHookNameAndDefaultPriority(): void
    {
        $action = new Action('init');

        self::assertSame('init', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function createsWithCustomPriority(): void
    {
        $action = new Action('init', priority: 20);

        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function extendsHook(): void
    {
        $action = new Action('init');

        self::assertInstanceOf(Hook::class, $action);
    }

    #[Test]
    public function isUsableAsAttribute(): void
    {
        $reflection = new \ReflectionClass(Action::class);
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
            #[Action('custom_hook')]
            public function handle(): void {}
        };

        $method = new \ReflectionMethod($class, 'handle');
        $attributes = $method->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);

        self::assertCount(1, $attributes);

        /** @var Hook $hook */
        $hook = $attributes[0]->newInstance();
        self::assertSame('custom_hook', $hook->hook);
        self::assertSame(HookType::Action, $hook->type);
    }
}
