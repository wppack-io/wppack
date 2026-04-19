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

namespace WPPack\Component\Messenger\Tests\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Messenger\Stamp\DelayStamp;
use WPPack\Component\Messenger\Test\TestMessageBus;
use WPPack\Component\Messenger\Tests\Fixtures\DummyMessage;

#[CoversClass(TestMessageBus::class)]
final class TestMessageBusTest extends TestCase
{
    #[Test]
    public function implementsMessageBusInterface(): void
    {
        $bus = new TestMessageBus();

        self::assertInstanceOf(MessageBusInterface::class, $bus);
    }

    #[Test]
    public function dispatchReturnsEnvelope(): void
    {
        $bus = new TestMessageBus();
        $message = new DummyMessage('hello', 1);

        $envelope = $bus->dispatch($message);

        self::assertInstanceOf(Envelope::class, $envelope);
        self::assertSame($message, $envelope->getMessage());
    }

    #[Test]
    public function dispatchWithStamps(): void
    {
        $bus = new TestMessageBus();
        $message = new DummyMessage('hello', 1);
        $stamp = new DelayStamp(500);

        $envelope = $bus->dispatch($message, [$stamp]);

        self::assertSame($stamp, $envelope->last(DelayStamp::class));
    }

    #[Test]
    public function dispatchExistingEnvelope(): void
    {
        $bus = new TestMessageBus();
        $message = new DummyMessage('hello', 1);
        $existingEnvelope = Envelope::wrap($message, [new DelayStamp(1000)]);

        $envelope = $bus->dispatch($existingEnvelope);

        self::assertSame($message, $envelope->getMessage());
        self::assertNotNull($envelope->last(DelayStamp::class));
    }

    #[Test]
    public function getDispatchedReturnsAllEnvelopes(): void
    {
        $bus = new TestMessageBus();
        $msg1 = new DummyMessage('first', 1);
        $msg2 = new DummyMessage('second', 2);

        $bus->dispatch($msg1);
        $bus->dispatch($msg2);

        $dispatched = $bus->getDispatched();

        self::assertCount(2, $dispatched);
        self::assertSame($msg1, $dispatched[0]->getMessage());
        self::assertSame($msg2, $dispatched[1]->getMessage());
    }

    #[Test]
    public function getDispatchedReturnsEmptyByDefault(): void
    {
        $bus = new TestMessageBus();

        self::assertSame([], $bus->getDispatched());
    }

    #[Test]
    public function getDispatchedMessagesReturnsMessageObjects(): void
    {
        $bus = new TestMessageBus();
        $msg1 = new DummyMessage('first', 1);
        $msg2 = new DummyMessage('second', 2);

        $bus->dispatch($msg1);
        $bus->dispatch($msg2);

        $messages = $bus->getDispatchedMessages();

        self::assertCount(2, $messages);
        self::assertSame($msg1, $messages[0]);
        self::assertSame($msg2, $messages[1]);
    }

    #[Test]
    public function getDispatchedMessagesReturnsEmptyByDefault(): void
    {
        $bus = new TestMessageBus();

        self::assertSame([], $bus->getDispatchedMessages());
    }

    #[Test]
    public function resetClearsDispatchedMessages(): void
    {
        $bus = new TestMessageBus();
        $bus->dispatch(new DummyMessage('hello', 1));
        $bus->dispatch(new DummyMessage('world', 2));

        self::assertCount(2, $bus->getDispatched());

        $bus->reset();

        self::assertSame([], $bus->getDispatched());
        self::assertSame([], $bus->getDispatchedMessages());
    }

    #[Test]
    public function dispatchAfterReset(): void
    {
        $bus = new TestMessageBus();
        $bus->dispatch(new DummyMessage('before', 1));
        $bus->reset();

        $msg = new DummyMessage('after', 2);
        $bus->dispatch($msg);

        self::assertCount(1, $bus->getDispatched());
        self::assertSame($msg, $bus->getDispatchedMessages()[0]);
    }
}
