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
use WPPack\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use WPPack\Component\Messenger\Middleware\MiddlewareStack;
use WPPack\Component\Messenger\Stamp\BusNameStamp;

#[CoversClass(AddBusNameStampMiddleware::class)]
final class AddBusNameStampMiddlewareTest extends TestCase
{
    #[Test]
    public function addsStampWhenMissing(): void
    {
        $middleware = new AddBusNameStampMiddleware('command');
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        $stamp = $result->last(BusNameStamp::class);
        self::assertNotNull($stamp);
        self::assertSame('command', $stamp->busName);
    }

    #[Test]
    public function doesNotAddStampWhenAlreadyPresent(): void
    {
        $middleware = new AddBusNameStampMiddleware('command');
        $envelope = Envelope::wrap(new \stdClass(), [new BusNameStamp('existing')]);
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertCount(1, $result->all(BusNameStamp::class));
        self::assertSame('existing', $result->last(BusNameStamp::class)->busName);
    }

    #[Test]
    public function usesDefaultBusName(): void
    {
        $middleware = new AddBusNameStampMiddleware();
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertSame('default', $result->last(BusNameStamp::class)->busName);
    }
}
