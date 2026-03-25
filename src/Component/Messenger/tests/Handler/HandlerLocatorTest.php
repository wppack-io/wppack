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

namespace WpPack\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Handler\HandlerDescriptor;
use WpPack\Component\Messenger\Handler\HandlerLocator;

#[CoversClass(HandlerLocator::class)]
#[CoversClass(HandlerDescriptor::class)]
final class HandlerLocatorTest extends TestCase
{
    #[Test]
    public function getHandlersReturnsRegisteredHandlers(): void
    {
        $handler = static fn(\stdClass $msg): string => 'result';
        $locator = new HandlerLocator([
            \stdClass::class => [$handler],
        ]);

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(1, $handlers);
        self::assertInstanceOf(HandlerDescriptor::class, $handlers[0]);
        self::assertSame('Closure', $handlers[0]->getName());
    }

    #[Test]
    public function getHandlersReturnsEmptyForUnknownMessage(): void
    {
        $locator = new HandlerLocator();

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertSame([], $handlers);
    }

    #[Test]
    public function addHandlerRegistersHandler(): void
    {
        $locator = new HandlerLocator();
        $locator->addHandler(\stdClass::class, static fn(\stdClass $msg): string => 'ok', 'myHandler');

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(1, $handlers);
        self::assertSame('myHandler', $handlers[0]->getName());
    }

    #[Test]
    public function addHandlerWithArrayCallableInfersName(): void
    {
        $locator = new HandlerLocator();
        $handler = new class {
            public function handle(\stdClass $msg): string
            {
                return 'ok';
            }
        };
        $locator->addHandler(\stdClass::class, [$handler, 'handle']);

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(1, $handlers);
        self::assertStringContainsString('::handle', $handlers[0]->getName());
    }

    #[Test]
    public function addHandlerWithStringCallableInfersName(): void
    {
        $locator = new HandlerLocator();
        $locator->addHandler(\stdClass::class, 'strlen');

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(1, $handlers);
        self::assertSame('strlen', $handlers[0]->getName());
    }

    #[Test]
    public function addHandlerWithInvocableObjectInfersName(): void
    {
        $locator = new HandlerLocator();
        $handler = new class {
            public function __invoke(\stdClass $msg): string
            {
                return 'invoked';
            }
        };
        $locator->addHandler(\stdClass::class, $handler);

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(1, $handlers);
        self::assertSame($handler::class, $handlers[0]->getName());
    }

    #[Test]
    public function getHandlersMatchesParentClassHandlers(): void
    {
        $locator = new HandlerLocator();
        // InvalidArgumentException extends LogicException extends Exception
        $locator->addHandler(\LogicException::class, static fn(object $msg): string => 'parent', 'parentHandler');

        $child = new \InvalidArgumentException('test');
        $handlers = iterator_to_array($locator->getHandlers($child));

        self::assertCount(1, $handlers);
        self::assertSame('parentHandler', $handlers[0]->getName());
    }

    #[Test]
    public function getHandlersMatchesInterfaceHandlers(): void
    {
        $locator = new HandlerLocator();
        $locator->addHandler(\Countable::class, static fn(object $msg): string => 'interface', 'interfaceHandler');

        $message = new \ArrayObject();
        $handlers = iterator_to_array($locator->getHandlers($message));

        self::assertCount(1, $handlers);
        self::assertSame('interfaceHandler', $handlers[0]->getName());
    }

    #[Test]
    public function getHandlersCombinesExactParentAndInterfaceMatches(): void
    {
        $locator = new HandlerLocator();
        // InvalidArgumentException extends LogicException extends Exception
        $locator->addHandler(\InvalidArgumentException::class, static fn(object $msg): string => 'exact', 'exactHandler');
        $locator->addHandler(\LogicException::class, static fn(object $msg): string => 'parent', 'parentHandler');
        $locator->addHandler(\Throwable::class, static fn(object $msg): string => 'interface', 'interfaceHandler');

        $message = new \InvalidArgumentException('test');
        $handlers = iterator_to_array($locator->getHandlers($message));

        self::assertCount(3, $handlers);
        $names = array_map(static fn(HandlerDescriptor $h): string => $h->getName(), $handlers);
        self::assertContains('exactHandler', $names);
        self::assertContains('parentHandler', $names);
        self::assertContains('interfaceHandler', $names);
    }

    #[Test]
    public function multipleHandlersForSameMessage(): void
    {
        $locator = new HandlerLocator([
            \stdClass::class => [
                static fn(\stdClass $msg): string => 'first',
                static fn(\stdClass $msg): string => 'second',
            ],
        ]);

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(2, $handlers);
    }

    #[Test]
    public function handlerDescriptorExecutesCallable(): void
    {
        $descriptor = new HandlerDescriptor(
            static fn(\stdClass $msg): string => 'executed',
            'testHandler',
        );

        $result = ($descriptor->getHandler())(new \stdClass());

        self::assertSame('executed', $result);
        self::assertSame('testHandler', $descriptor->getName());
    }

    #[Test]
    public function addHandlerWithStaticArrayCallableInfersName(): void
    {
        $locator = new HandlerLocator();
        $locator->addHandler(\stdClass::class, [self::class, 'staticHandlerMethod']);

        $handlers = iterator_to_array($locator->getHandlers(new \stdClass()));

        self::assertCount(1, $handlers);
        self::assertSame(self::class . '::staticHandlerMethod', $handlers[0]->getName());
    }

    public static function staticHandlerMethod(\stdClass $msg): string
    {
        return 'static';
    }
}
