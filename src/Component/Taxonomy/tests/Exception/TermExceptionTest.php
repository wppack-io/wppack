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

namespace WPPack\Component\Taxonomy\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Taxonomy\Exception\ExceptionInterface;
use WPPack\Component\Taxonomy\Exception\TermException;

#[CoversClass(TermException::class)]
final class TermExceptionTest extends TestCase
{
    #[Test]
    public function defaultConstructorProducesEmptyWpErrorMetadata(): void
    {
        $e = new TermException('boom');

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
        $e = new TermException('effect', previous: $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    #[Test]
    public function fromWpErrorCopiesCodesAndMessages(): void
    {
        $error = new \WP_Error();
        $error->add('term_exists', 'A term with the name already exists.');
        $error->add('invalid_taxonomy', 'Invalid taxonomy.');

        $e = TermException::fromWpError($error);

        self::assertStringContainsString('term', $e->getMessage());
        self::assertSame(['term_exists', 'invalid_taxonomy'], $e->getWpErrorCodes());
        self::assertCount(2, $e->getWpErrorMessages());
    }

    #[Test]
    public function fromWpErrorEmptyErrorStillProducesException(): void
    {
        $e = TermException::fromWpError(new \WP_Error());

        self::assertSame('', $e->getMessage());
        self::assertSame([], $e->getWpErrorCodes());
    }
}
