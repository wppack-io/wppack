<?php

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\WordPressEvent;

final class WordPressEventTest extends TestCase
{
    #[Test]
    public function extendsEvent(): void
    {
        $event = new WordPressEvent('save_post', [1, null, true]);

        self::assertInstanceOf(Event::class, $event);
    }

    #[Test]
    public function storesHookNameAndArgs(): void
    {
        $event = new WordPressEvent('save_post', [42, 'post_object', false]);

        self::assertSame('save_post', $event->hookName);
        self::assertSame([42, 'post_object', false], $event->args);
    }

    #[Test]
    public function magicGetterReturnsArgByMap(): void
    {
        $event = new class ('save_post', [42, 'the_post', true]) extends WordPressEvent {
            protected array $argMap = [
                'postId' => 0,
                'post' => 1,
                'update' => 2,
            ];
        };

        self::assertSame(42, $event->getPostId());
        self::assertSame('the_post', $event->getPost());
        self::assertTrue($event->getUpdate());
    }

    #[Test]
    public function magicGetterThrowsForUnknownMethod(): void
    {
        $event = new WordPressEvent('init', []);

        $this->expectException(\BadMethodCallException::class);
        $event->getUnknown();
    }

    #[Test]
    public function magicGetterThrowsForNonGetterMethod(): void
    {
        $event = new WordPressEvent('init', []);

        $this->expectException(\BadMethodCallException::class);
        $event->doSomething();
    }

    #[Test]
    public function magicGetterReturnsNullArgValue(): void
    {
        $event = new class ('hook', [null]) extends WordPressEvent {
            protected array $argMap = [
                'value' => 0,
            ];
        };

        self::assertNull($event->getValue());
    }

    #[Test]
    public function magicGetterThrowsWhenArgIndexOutOfBounds(): void
    {
        $event = new class ('hook', []) extends WordPressEvent {
            protected array $argMap = [
                'missing' => 5,
            ];
        };

        $this->expectException(\BadMethodCallException::class);
        $event->getMissing();
    }

    #[Test]
    public function hookNameConstant(): void
    {
        self::assertSame('', WordPressEvent::HOOK_NAME);
    }

    #[Test]
    public function subclassCanOverrideHookName(): void
    {
        $class = new class ('test', []) extends WordPressEvent {
            public const HOOK_NAME = 'custom_hook';
        };

        self::assertSame('custom_hook', $class::HOOK_NAME);
    }

    #[Test]
    public function isPropagationStoppable(): void
    {
        $event = new WordPressEvent('init', []);
        self::assertFalse($event->isPropagationStopped());

        $event->stopPropagation();
        self::assertTrue($event->isPropagationStopped());
    }
}
