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
use Psr\Log\LoggerInterface;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Exception\HandlerFailedException;
use WPPack\Component\Messenger\Handler\HandlerLocator;
use WPPack\Component\Messenger\Middleware\HandleMessageMiddleware;
use WPPack\Component\Messenger\Middleware\MiddlewareStack;
use WPPack\Component\Messenger\Stamp\HandledStamp;

#[CoversClass(HandleMessageMiddleware::class)]
final class HandleMessageMiddlewareLoggerTest extends TestCase
{
    #[Test]
    public function logsInfoOnSuccessfulHandle(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Message {class} handled by {handler}',
                self::callback(static function (array $context): bool {
                    return $context['class'] === \stdClass::class
                        && $context['handler'] === 'Closure';
                }),
            );

        $locator = new HandlerLocator([
            \stdClass::class => [
                static fn(\stdClass $msg): string => 'ok',
            ],
        ]);

        $middleware = new HandleMessageMiddleware($locator, logger: $logger);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $result = $middleware->handle($envelope, $stack);

        self::assertNotNull($result->last(HandledStamp::class));
    }

    #[Test]
    public function logsErrorOnHandlerFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Message {class} failed in handler {handler}: {error}',
                self::callback(static function (array $context): bool {
                    return $context['class'] === \stdClass::class
                        && $context['handler'] === 'Closure'
                        && $context['error'] === 'Handler exploded';
                }),
            );

        $locator = new HandlerLocator([
            \stdClass::class => [
                static function (\stdClass $msg): never {
                    throw new \RuntimeException('Handler exploded');
                },
            ],
        ]);

        $middleware = new HandleMessageMiddleware($locator, logger: $logger);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $this->expectException(HandlerFailedException::class);

        $middleware->handle($envelope, $stack);
    }

    #[Test]
    public function logsMultipleHandlersCombined(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');
        $logger->expects(self::once())->method('error');

        $locator = new HandlerLocator([
            \stdClass::class => [
                static fn(\stdClass $msg): string => 'success',
                static function (\stdClass $msg): never {
                    throw new \RuntimeException('Fail');
                },
            ],
        ]);

        $middleware = new HandleMessageMiddleware($locator, logger: $logger);
        $envelope = Envelope::wrap(new \stdClass());
        $stack = new MiddlewareStack([]);

        $this->expectException(HandlerFailedException::class);

        $middleware->handle($envelope, $stack);
    }
}
