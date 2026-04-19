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

namespace WPPack\Component\Messenger\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Stamp\BusNameStamp;
use WPPack\Component\Messenger\Stamp\DelayStamp;
use WPPack\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(Envelope::class)]
final class EnvelopeTest extends TestCase
{
    #[Test]
    public function wrapCreatesEnvelopeFromMessage(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);

        self::assertSame($message, $envelope->getMessage());
        self::assertSame([], $envelope->all());
    }

    #[Test]
    public function wrapWithStamps(): void
    {
        $message = new \stdClass();
        $stamp = new DelayStamp(1000);
        $envelope = Envelope::wrap($message, [$stamp]);

        self::assertSame($stamp, $envelope->last(DelayStamp::class));
        self::assertCount(1, $envelope->all(DelayStamp::class));
    }

    #[Test]
    public function wrapReturnsExistingEnvelopeWithAdditionalStamps(): void
    {
        $message = new \stdClass();
        $original = Envelope::wrap($message, [new DelayStamp(1000)]);

        $busStamp = new BusNameStamp('test');
        $wrapped = Envelope::wrap($original, [$busStamp]);

        self::assertSame($message, $wrapped->getMessage());
        self::assertNotNull($wrapped->last(DelayStamp::class));
        self::assertSame($busStamp, $wrapped->last(BusNameStamp::class));
    }

    #[Test]
    public function withReturnsNewImmutableEnvelope(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message);
        $stamp = new DelayStamp(500);

        $newEnvelope = $envelope->with($stamp);

        self::assertNotSame($envelope, $newEnvelope);
        self::assertNull($envelope->last(DelayStamp::class));
        self::assertSame($stamp, $newEnvelope->last(DelayStamp::class));
    }

    #[Test]
    public function withoutAllRemovesStampsByClass(): void
    {
        $message = new \stdClass();
        $envelope = Envelope::wrap($message, [
            new DelayStamp(100),
            new DelayStamp(200),
            new BusNameStamp('test'),
        ]);

        $newEnvelope = $envelope->withoutAll(DelayStamp::class);

        self::assertSame([], $newEnvelope->all(DelayStamp::class));
        self::assertNotNull($newEnvelope->last(BusNameStamp::class));
        // Original unchanged
        self::assertCount(2, $envelope->all(DelayStamp::class));
    }

    #[Test]
    public function lastReturnsLastStampOfGivenClass(): void
    {
        $stamp1 = new HandledStamp('result1', 'handler1');
        $stamp2 = new HandledStamp('result2', 'handler2');
        $envelope = Envelope::wrap(new \stdClass(), [$stamp1, $stamp2]);

        $last = $envelope->last(HandledStamp::class);

        self::assertSame($stamp2, $last);
    }

    #[Test]
    public function lastReturnsNullWhenNoStampFound(): void
    {
        $envelope = Envelope::wrap(new \stdClass());

        self::assertNull($envelope->last(DelayStamp::class));
    }

    #[Test]
    public function allReturnsStampsByClass(): void
    {
        $stamp1 = new HandledStamp('r1', 'h1');
        $stamp2 = new HandledStamp('r2', 'h2');
        $bus = new BusNameStamp('default');
        $envelope = Envelope::wrap(new \stdClass(), [$stamp1, $stamp2, $bus]);

        self::assertCount(2, $envelope->all(HandledStamp::class));
        self::assertCount(1, $envelope->all(BusNameStamp::class));
    }

    #[Test]
    public function allWithoutClassReturnsAllStampsFlat(): void
    {
        $stamp1 = new DelayStamp(100);
        $stamp2 = new BusNameStamp('test');
        $envelope = Envelope::wrap(new \stdClass(), [$stamp1, $stamp2]);

        $all = $envelope->all();

        self::assertCount(2, $all);
        self::assertContains($stamp1, $all);
        self::assertContains($stamp2, $all);
    }
}
