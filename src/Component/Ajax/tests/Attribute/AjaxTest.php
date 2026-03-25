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

namespace WpPack\Component\Ajax\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\Attribute\Ajax;

final class AjaxTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $handler = new Ajax(action: 'my_action');

        self::assertSame('my_action', $handler->action);
        self::assertSame(Access::Public, $handler->access);
        self::assertNull($handler->checkReferer);
        self::assertSame(10, $handler->priority);
    }

    #[Test]
    public function authenticatedAccess(): void
    {
        $handler = new Ajax(action: 'my_action', access: Access::Authenticated);

        self::assertSame(Access::Authenticated, $handler->access);
    }

    #[Test]
    public function guestAccess(): void
    {
        $handler = new Ajax(action: 'my_action', access: Access::Guest);

        self::assertSame(Access::Guest, $handler->access);
    }

    #[Test]
    public function allParametersCustomized(): void
    {
        $handler = new Ajax(
            action: 'delete_item',
            access: Access::Authenticated,
            checkReferer: 'delete_item_nonce',
            priority: 5,
        );

        self::assertSame('delete_item', $handler->action);
        self::assertSame(Access::Authenticated, $handler->access);
        self::assertSame('delete_item_nonce', $handler->checkReferer);
        self::assertSame(5, $handler->priority);
    }

    #[Test]
    public function isRepeatableAttribute(): void
    {
        $class = new class {
            #[Ajax(action: 'action_one')]
            #[Ajax(action: 'action_two', access: Access::Authenticated)]
            public function handle(): void {}
        };

        $method = new \ReflectionMethod($class, 'handle');
        $attributes = $method->getAttributes(Ajax::class);

        self::assertCount(2, $attributes);
        self::assertSame('action_one', $attributes[0]->newInstance()->action);
        self::assertSame('action_two', $attributes[1]->newInstance()->action);
    }

    #[Test]
    public function targetsMethodsOnly(): void
    {
        $reflection = new \ReflectionClass(Ajax::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        self::assertSame(
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $attribute->flags,
        );
    }
}
