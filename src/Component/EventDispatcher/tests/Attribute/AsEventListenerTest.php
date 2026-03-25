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

namespace WpPack\Component\EventDispatcher\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;

final class AsEventListenerTest extends TestCase
{
    #[Test]
    public function isUsableAsClassAttribute(): void
    {
        $reflection = new \ReflectionClass(AsEventListener::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        self::assertNotSame(0, $attribute->flags & \Attribute::TARGET_CLASS);
    }

    #[Test]
    public function isUsableAsMethodAttribute(): void
    {
        $reflection = new \ReflectionClass(AsEventListener::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        self::assertNotSame(0, $attribute->flags & \Attribute::TARGET_METHOD);
    }

    #[Test]
    public function isRepeatable(): void
    {
        $reflection = new \ReflectionClass(AsEventListener::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        /** @var \Attribute $attribute */
        $attribute = $attributes[0]->newInstance();
        self::assertNotSame(0, $attribute->flags & \Attribute::IS_REPEATABLE);
    }

    #[Test]
    public function defaultValues(): void
    {
        $listener = new AsEventListener();

        self::assertNull($listener->event);
        self::assertNull($listener->method);
        self::assertSame(10, $listener->priority);
        self::assertSame(1, $listener->acceptedArgs);
    }

    #[Test]
    public function customValues(): void
    {
        $listener = new AsEventListener(
            event: 'save_post',
            method: 'onSavePost',
            priority: 20,
            acceptedArgs: 3,
        );

        self::assertSame('save_post', $listener->event);
        self::assertSame('onSavePost', $listener->method);
        self::assertSame(20, $listener->priority);
        self::assertSame(3, $listener->acceptedArgs);
    }

    #[Test]
    public function isDetectableOnClass(): void
    {
        $class = new #[AsEventListener(event: 'test_event')] class {};

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsEventListener::class);

        self::assertCount(1, $attributes);

        /** @var AsEventListener $instance */
        $instance = $attributes[0]->newInstance();
        self::assertSame('test_event', $instance->event);
    }

    #[Test]
    public function isDetectableOnMethod(): void
    {
        $class = new class {
            #[AsEventListener(event: 'init', priority: 5)]
            public function onInit(): void {}
        };

        $reflection = new \ReflectionMethod($class, 'onInit');
        $attributes = $reflection->getAttributes(AsEventListener::class);

        self::assertCount(1, $attributes);

        /** @var AsEventListener $instance */
        $instance = $attributes[0]->newInstance();
        self::assertSame('init', $instance->event);
        self::assertSame(5, $instance->priority);
    }

    #[Test]
    public function multipleAttributesOnMethod(): void
    {
        $class = new class {
            #[AsEventListener(event: 'event_one')]
            #[AsEventListener(event: 'event_two', priority: 20)]
            public function handle(): void {}
        };

        $reflection = new \ReflectionMethod($class, 'handle');
        $attributes = $reflection->getAttributes(AsEventListener::class);

        self::assertCount(2, $attributes);
    }
}
