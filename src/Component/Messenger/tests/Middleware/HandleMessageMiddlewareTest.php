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

namespace WPPack\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Exception\HandlerFailedException;
use WPPack\Component\Messenger\Exception\NoHandlerForMessageException;
use WPPack\Component\Messenger\Handler\HandlerLocator;
use WPPack\Component\Messenger\Middleware\HandleMessageMiddleware;
use WPPack\Component\Messenger\Middleware\MiddlewareStack;
use WPPack\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(HandleMessageMiddleware::class)]
final class HandleMessageMiddlewareTest extends TestCase
{
    #[Test]
    public function handlerIsExecutedAndStampAdded(): void
    {
        $locator = new HandlerLocator([
            \stdClass::class => [
                static fn(\stdClass $msg): string => 'handled',
            ],
        ]);

        $middleware = new HandleMessageMiddleware($locator);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        $handledStamp = $result->last(HandledStamp::class);
        self::assertNotNull($handledStamp);
        self::assertSame('handled', $handledStamp->result);
        self::assertSame('Closure', $handledStamp->handlerName);
    }

    #[Test]
    public function multipleHandlersAreExecuted(): void
    {
        $locator = new HandlerLocator([
            \stdClass::class => [
                static fn(\stdClass $msg): string => 'first',
                static fn(\stdClass $msg): string => 'second',
            ],
        ]);

        $middleware = new HandleMessageMiddleware($locator);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        $stamps = $result->all(HandledStamp::class);
        self::assertCount(2, $stamps);
        self::assertSame('first', $stamps[0]->result);
        self::assertSame('second', $stamps[1]->result);
    }

    #[Test]
    public function throwsNoHandlerForMessageExceptionByDefault(): void
    {
        $locator = new HandlerLocator();
        $middleware = new HandleMessageMiddleware($locator);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $this->expectException(NoHandlerForMessageException::class);
        $this->expectExceptionMessage('No handler for message "stdClass".');

        $middleware->handle($envelope, $stack);
    }

    #[Test]
    public function allowsNoHandlersWhenConfigured(): void
    {
        $locator = new HandlerLocator();
        $middleware = new HandleMessageMiddleware($locator, allowNoHandlers: true);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertNull($result->last(HandledStamp::class));
    }

    #[Test]
    public function throwsHandlerFailedExceptionOnError(): void
    {
        $locator = new HandlerLocator([
            \stdClass::class => [
                static function (\stdClass $msg): never {
                    throw new \RuntimeException('Handler error');
                },
            ],
        ]);

        $middleware = new HandleMessageMiddleware($locator);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        try {
            $middleware->handle($envelope, $stack);
            self::fail('Expected HandlerFailedException was not thrown.');
        } catch (HandlerFailedException $e) {
            self::assertCount(1, $e->getExceptions());
            self::assertSame('Handler error', $e->getExceptions()[0]->getMessage());
            self::assertSame($envelope->getMessage(), $e->getEnvelope()->getMessage());
        }
    }
}
