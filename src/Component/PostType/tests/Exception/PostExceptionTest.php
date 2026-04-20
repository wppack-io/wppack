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

namespace WPPack\Component\PostType\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\PostType\Exception\ExceptionInterface;
use WPPack\Component\PostType\Exception\PostException;

#[CoversClass(PostException::class)]
final class PostExceptionTest extends TestCase
{
    #[Test]
    public function defaultConstructorProducesEmptyWpErrorMetadata(): void
    {
        $e = new PostException('boom');

        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertSame('boom', $e->getMessage());
        self::assertSame([], $e->getWpErrorCodes());
        self::assertSame([], $e->getWpErrorMessages());
    }

    #[Test]
    public function previousExceptionIsPreserved(): void
    {
        $previous = new \LogicException('cause');
        $e = new PostException('effect', previous: $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function fromWpErrorCopiesCodesAndMessages(): void
    {
        $error = new \WP_Error();
        $error->add('empty_content', 'Content, title, and excerpt are empty.');
        $error->add('invalid_date', 'Invalid post date.');

        $e = PostException::fromWpError($error);

        self::assertStringContainsString('Content', $e->getMessage());
        self::assertSame(['empty_content', 'invalid_date'], $e->getWpErrorCodes());
        self::assertCount(2, $e->getWpErrorMessages());
    }

    #[Test]
    public function fromWpErrorEmptyErrorStillProducesException(): void
    {
        $e = PostException::fromWpError(new \WP_Error());

        self::assertSame('', $e->getMessage());
        self::assertSame([], $e->getWpErrorCodes());
    }
}
