<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Handler\HandlerDescriptor;

#[CoversClass(HandlerDescriptor::class)]
final class HandlerDescriptorTest extends TestCase
{
    #[Test]
    public function constructWithClosureAndName(): void
    {
        $handler = static fn(\stdClass $msg): string => 'result';
        $descriptor = new HandlerDescriptor($handler, 'myHandler');

        self::assertSame('myHandler', $descriptor->getName());
        self::assertInstanceOf(\Closure::class, $descriptor->getHandler());
    }

    #[Test]
    public function constructWithDefaultEmptyName(): void
    {
        $handler = static fn(\stdClass $msg): string => 'result';
        $descriptor = new HandlerDescriptor($handler);

        self::assertSame('', $descriptor->getName());
    }

    #[Test]
    public function getHandlerReturnsClosure(): void
    {
        $handler = static fn(\stdClass $msg): string => 'executed';
        $descriptor = new HandlerDescriptor($handler, 'test');

        $result = ($descriptor->getHandler())(new \stdClass());

        self::assertSame('executed', $result);
    }

    #[Test]
    public function constructWithInvocableObject(): void
    {
        $invocable = new class {
            public function __invoke(\stdClass $msg): string
            {
                return 'invoked';
            }
        };

        $descriptor = new HandlerDescriptor($invocable, 'invocableHandler');

        $result = ($descriptor->getHandler())(new \stdClass());

        self::assertSame('invoked', $result);
        self::assertSame('invocableHandler', $descriptor->getName());
    }

    #[Test]
    public function constructWithArrayCallable(): void
    {
        $object = new class {
            public function handle(\stdClass $msg): string
            {
                return 'array-handled';
            }
        };

        $descriptor = new HandlerDescriptor([$object, 'handle'], 'arrayHandler');

        $result = ($descriptor->getHandler())(new \stdClass());

        self::assertSame('array-handled', $result);
        self::assertSame('arrayHandler', $descriptor->getName());
    }

    #[Test]
    public function handlerAlwaysConvertedToClosure(): void
    {
        $descriptor = new HandlerDescriptor('strlen', 'strlenHandler');

        self::assertInstanceOf(\Closure::class, $descriptor->getHandler());
    }

    #[Test]
    public function handlerWithNullResult(): void
    {
        $handler = static fn(\stdClass $msg): ?string => null;
        $descriptor = new HandlerDescriptor($handler, 'nullHandler');

        $result = ($descriptor->getHandler())(new \stdClass());

        self::assertNull($result);
    }
}
