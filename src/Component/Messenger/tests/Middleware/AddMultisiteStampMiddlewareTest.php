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
use WPPack\Component\Messenger\Middleware\AddMultisiteStampMiddleware;
use WPPack\Component\Messenger\Middleware\MiddlewareStack;
use WPPack\Component\Messenger\Stamp\MultisiteStamp;

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

    #[Test]
    public function addsStampWhenNotPresent(): void
    {
        $middleware = new AddMultisiteStampMiddleware();
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        $stamp = $result->last(MultisiteStamp::class);
        self::assertNotNull($stamp);
        self::assertSame(get_current_blog_id(), $stamp->blogId);
    }
}
