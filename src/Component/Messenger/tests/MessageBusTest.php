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

namespace WpPack\Component\Messenger\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\MessageBus;
use WpPack\Component\Messenger\Middleware\MiddlewareInterface;
use WpPack\Component\Messenger\Middleware\StackInterface;
use WpPack\Component\Messenger\Stamp\BusNameStamp;
use WpPack\Component\Messenger\Stamp\DelayStamp;

#[CoversClass(MessageBus::class)]
final class MessageBusTest extends TestCase
{
    #[Test]
    public function dispatchWithNoMiddleware(): void
    {
        $bus = new MessageBus();
        $message = new \stdClass();

        $envelope = $bus->dispatch($message);

        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertSame($message, $envelope->getMessage());
    }

    #[Test]
    public function dispatchThroughMiddlewareChain(): void
    {
        $middleware1 = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $envelope->with(new BusNameStamp('first'));

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $middleware2 = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $envelope->with(new DelayStamp(500));

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $bus = new MessageBus([$middleware1, $middleware2]);
        $envelope = $bus->dispatch(new \stdClass());

        self::assertNotNull($envelope->last(BusNameStamp::class));
        self::assertSame('first', $envelope->last(BusNameStamp::class)->busName);
        self::assertNotNull($envelope->last(DelayStamp::class));
        self::assertSame(500, $envelope->last(DelayStamp::class)->delayInMilliseconds);
    }

    #[Test]
    public function dispatchWithExistingEnvelope(): void
    {
        $message = new \stdClass();
        $existingEnvelope = Envelope::wrap($message, [new BusNameStamp('pre-stamped')]);

        $bus = new MessageBus();
        $envelope = $bus->dispatch($existingEnvelope);

        self::assertSame($message, $envelope->getMessage());
        self::assertSame('pre-stamped', $envelope->last(BusNameStamp::class)->busName);
    }

    #[Test]
    public function dispatchWithStamps(): void
    {
        $bus = new MessageBus();
        $message = new \stdClass();
        $delay = new DelayStamp(1000);

        $envelope = $bus->dispatch($message, [$delay]);

        self::assertSame($delay, $envelope->last(DelayStamp::class));
    }

    #[Test]
    public function constructorAcceptsTraversable(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $envelope = $envelope->with(new BusNameStamp('from-generator'));

                return $stack->next()->handle($envelope, $stack);
            }
        };

        $generator = (static function () use ($middleware): \Generator {
            yield $middleware;
        })();

        $bus = new MessageBus($generator);
        $envelope = $bus->dispatch(new \stdClass());

        self::assertNotNull($envelope->last(BusNameStamp::class));
        self::assertSame('from-generator', $envelope->last(BusNameStamp::class)->busName);
    }
}
