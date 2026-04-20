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

namespace WPPack\Component\Messenger\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Exception\ExceptionInterface;
use WPPack\Component\Messenger\Exception\InvalidArgumentException;
use WPPack\Component\Messenger\Exception\MessageDecodingFailedException;
use WPPack\Component\Messenger\Exception\MessageEncodingFailedException;
use WPPack\Component\Messenger\Exception\NoHandlerForMessageException;
use WPPack\Component\Messenger\Exception\TransportException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(MessageDecodingFailedException::class)]
#[CoversClass(MessageEncodingFailedException::class)]
#[CoversClass(NoHandlerForMessageException::class)]
#[CoversClass(TransportException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function runtimeDescendantsImplementMarker(): void
    {
        foreach ([TransportException::class, MessageDecodingFailedException::class, MessageEncodingFailedException::class] as $class) {
            $e = new $class('boom');
            self::assertInstanceOf(\RuntimeException::class, $e);
            self::assertInstanceOf(ExceptionInterface::class, $e);
            self::assertSame('boom', $e->getMessage());
        }
    }

    #[Test]
    public function noHandlerForMessageExceptionIsLogicException(): void
    {
        $e = new NoHandlerForMessageException('no handler');

        self::assertInstanceOf(\LogicException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }

    #[Test]
    public function invalidArgumentExtendsCoreAndImplementsMarker(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }
}
