<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Access;
use WpPack\Component\Ajax\Attribute\AjaxHandler;

final class AjaxHandlerTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $handler = new AjaxHandler(action: 'my_action');

        self::assertSame('my_action', $handler->action);
        self::assertSame(Access::Public, $handler->access);
        self::assertNull($handler->capability);
        self::assertNull($handler->checkReferer);
        self::assertSame(10, $handler->priority);
    }

    #[Test]
    public function authenticatedAccess(): void
    {
        $handler = new AjaxHandler(action: 'my_action', access: Access::Authenticated);

        self::assertSame(Access::Authenticated, $handler->access);
    }

    #[Test]
    public function guestAccess(): void
    {
        $handler = new AjaxHandler(action: 'my_action', access: Access::Guest);

        self::assertSame(Access::Guest, $handler->access);
    }

    #[Test]
    public function allParametersCustomized(): void
    {
        $handler = new AjaxHandler(
            action: 'delete_item',
            access: Access::Authenticated,
            capability: 'delete_posts',
            checkReferer: 'delete_item_nonce',
            priority: 5,
        );

        self::assertSame('delete_item', $handler->action);
        self::assertSame(Access::Authenticated, $handler->access);
        self::assertSame('delete_posts', $handler->capability);
        self::assertSame('delete_item_nonce', $handler->checkReferer);
        self::assertSame(5, $handler->priority);
    }

    #[Test]
    public function isRepeatableAttribute(): void
    {
        $class = new class {
            #[AjaxHandler(action: 'action_one')]
            #[AjaxHandler(action: 'action_two', access: Access::Authenticated)]
            public function handle(): void {}
        };

        $method = new \ReflectionMethod($class, 'handle');
        $attributes = $method->getAttributes(AjaxHandler::class);

        self::assertCount(2, $attributes);
        self::assertSame('action_one', $attributes[0]->newInstance()->action);
        self::assertSame('action_two', $attributes[1]->newInstance()->action);
    }

    #[Test]
    public function targetsMethodsOnly(): void
    {
        $reflection = new \ReflectionClass(AjaxHandler::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        self::assertSame(
            \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE,
            $attribute->flags,
        );
    }
}
