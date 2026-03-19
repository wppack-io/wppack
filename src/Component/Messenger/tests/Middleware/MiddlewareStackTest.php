<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Middleware\MiddlewareInterface;
use WpPack\Component\Messenger\Middleware\MiddlewareStack;
use WpPack\Component\Messenger\Middleware\StackInterface;
use WpPack\Component\Messenger\Stamp\BusNameStamp;
use WpPack\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(MiddlewareStack::class)]
final class MiddlewareStackTest extends TestCase
{
    #[Test]
    public function emptyStackReturnsTerminalMiddleware(): void
    {
        $stack = new MiddlewareStack([]);
        $envelope = Envelope::wrap(new \stdClass());

        $middleware = $stack->next();
        $result = $middleware->handle($envelope, $stack);

        // Terminal middleware returns envelope unchanged
        self::assertSame($envelope->getMessage(), $result->getMessage());
    }

    #[Test]
    public function nextReturnsMiddlewaresInOrder(): void
    {
        $first = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope->with(new BusNameStamp('first'));
            }
        };

        $second = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope->with(new DelayStamp(100));
            }
        };

        $stack = new MiddlewareStack([$first, $second]);
        $envelope = Envelope::wrap(new \stdClass());

        $middleware1 = $stack->next();
        self::assertSame($first, $middleware1);

        $middleware2 = $stack->next();
        self::assertSame($second, $middleware2);
    }

    #[Test]
    public function terminalMiddlewareReturnedAfterAllConsumed(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope->with(new BusNameStamp('only'));
            }
        };

        $stack = new MiddlewareStack([$middleware]);

        // First call returns the middleware
        self::assertSame($middleware, $stack->next());

        // Second call returns the terminal middleware (anonymous class)
        $terminal = $stack->next();
        self::assertNotSame($middleware, $terminal);
        self::assertInstanceOf(MiddlewareInterface::class, $terminal);

        // Terminal just returns the envelope as-is
        $envelope = Envelope::wrap(new \stdClass());
        $result = $terminal->handle($envelope, $stack);
        self::assertSame($envelope->getMessage(), $result->getMessage());
    }

    #[Test]
    public function constructorAcceptsTraversable(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope->with(new BusNameStamp('from-generator'));
            }
        };

        $generator = (static function () use ($middleware): \Generator {
            yield $middleware;
        })();

        $stack = new MiddlewareStack($generator);

        self::assertSame($middleware, $stack->next());
    }

    #[Test]
    public function middlewareChainDelegation(): void
    {
        $first = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $envelope->with(new BusNameStamp('first'));

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $second = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $envelope->with(new DelayStamp(200));

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $stack = new MiddlewareStack([$first, $second]);
        $envelope = Envelope::wrap(new \stdClass());

        $result = $stack->next()->handle($envelope, $stack);

        self::assertNotNull($result->last(BusNameStamp::class));
        self::assertSame('first', $result->last(BusNameStamp::class)->busName);
        self::assertNotNull($result->last(DelayStamp::class));
        self::assertSame(200, $result->last(DelayStamp::class)->delayInMilliseconds);
    }

    #[Test]
    public function constructorWithArrayReindexes(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                return $envelope;
            }
        };

        // Non-sequential keys
        $stack = new MiddlewareStack([5 => $middleware]);

        self::assertSame($middleware, $stack->next());
    }
}
