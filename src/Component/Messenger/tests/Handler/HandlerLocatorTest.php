<?php

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
}
