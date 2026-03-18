<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Middleware\AddMultisiteStampMiddleware;
use WpPack\Component\Messenger\Middleware\MiddlewareStack;
use WpPack\Component\Messenger\Stamp\MultisiteStamp;

#[CoversClass(AddMultisiteStampMiddleware::class)]
final class AddMultisiteStampMiddlewareTest extends TestCase
{
    #[Test]
    public function doesNotAddStampWhenAlreadyPresent(): void
    {
        $middleware = new AddMultisiteStampMiddleware();
        $envelope = Envelope::wrap(new \stdClass(), [new MultisiteStamp(5)]);
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertCount(1, $result->all(MultisiteStamp::class));
        self::assertSame(5, $result->last(MultisiteStamp::class)->blogId);
    }
}
