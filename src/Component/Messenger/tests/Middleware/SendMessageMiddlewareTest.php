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
use WPPack\Component\Messenger\Middleware\MiddlewareStack;
use WPPack\Component\Messenger\Middleware\SendMessageMiddleware;
use WPPack\Component\Messenger\Stamp\ReceivedStamp;
use WPPack\Component\Messenger\Stamp\SentStamp;
use WPPack\Component\Messenger\Stamp\TransportStamp;
use WPPack\Component\Messenger\Transport\SyncTransport;
use WPPack\Component\Messenger\Transport\TransportInterface;

#[CoversClass(SendMessageMiddleware::class)]
final class SendMessageMiddlewareTest extends TestCase
{
    #[Test]
    public function skipsWhenReceivedStampPresent(): void
    {
        $middleware = new SendMessageMiddleware();
        $envelope = Envelope::wrap(new \stdClass(), [new ReceivedStamp('sqs')]);
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        // Should pass through without adding SentStamp
        self::assertNull($result->last(SentStamp::class));
        self::assertNotNull($result->last(ReceivedStamp::class));
    }

    #[Test]
    public function skipsWhenSentStampPresent(): void
    {
        $middleware = new SendMessageMiddleware();
        $envelope = Envelope::wrap(new \stdClass(), [new SentStamp('sqs')]);
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertCount(1, $result->all(SentStamp::class));
    }

    #[Test]
    public function sendsToAsyncTransportAndStopsMiddlewareChain(): void
    {
        $sentEnvelopes = [];
        $asyncTransport = new class ($sentEnvelopes) implements TransportInterface {
            /** @var list<Envelope> */
            private array $sent;

            public function __construct(
                private array &$sentRef,
            ) {
                $this->sent = &$this->sentRef;
            }

            public function getName(): string
            {
                return 'async';
            }

            public function send(Envelope $envelope): Envelope
            {
                $this->sentRef[] = $envelope;

                return $envelope->with(new SentStamp($this->getName()));
            }
        };

        $middleware = new SendMessageMiddleware(['async' => $asyncTransport]);
        $envelope = Envelope::wrap(new \stdClass(), [new TransportStamp('async')]);
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertNotNull($result->last(SentStamp::class));
        self::assertSame('async', $result->last(SentStamp::class)->transportName);
        self::assertCount(1, $sentEnvelopes);
    }

    #[Test]
    public function sendsToSyncTransportAndContinuesToNextMiddleware(): void
    {
        $syncTransport = new SyncTransport();
        $middleware = new SendMessageMiddleware(['sync' => $syncTransport]);
        $envelope = Envelope::wrap(new \stdClass(), [new TransportStamp('sync')]);
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertNotNull($result->last(SentStamp::class));
        self::assertSame('sync', $result->last(SentStamp::class)->transportName);
    }

    #[Test]
    public function noTransportContinuesToNextMiddleware(): void
    {
        $middleware = new SendMessageMiddleware();
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertNull($result->last(SentStamp::class));
    }
}
