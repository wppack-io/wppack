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

namespace WPPack\Component\Media\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Media\Exception\AttachmentException;
use WPPack\Component\Media\Exception\ExceptionInterface;

#[CoversClass(AttachmentException::class)]
final class AttachmentExceptionTest extends TestCase
{
    #[Test]
    public function defaultConstructorProducesEmptyWpErrorMetadata(): void
    {
        $e = new AttachmentException('boom');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('boom', $e->getMessage());
        self::assertSame([], $e->getWpErrorCodes());
        self::assertSame([], $e->getWpErrorMessages());
    }

    #[Test]
    public function fromWpErrorCopiesCodesAndMessages(): void
    {
        $error = new \WP_Error();
        $error->add('upload_error', 'The file upload failed.');
        $error->add('invalid_mime', 'Invalid mime type.');

        $e = AttachmentException::fromWpError($error);

        self::assertStringContainsString('upload', $e->getMessage());
        self::assertSame(['upload_error', 'invalid_mime'], $e->getWpErrorCodes());
        self::assertCount(2, $e->getWpErrorMessages());
    }

    #[Test]
    public function previousExceptionIsPreserved(): void
    {
        $previous = new \LogicException('cause');
        $e = new AttachmentException('x', previous: $previous);

        self::assertSame($previous, $e->getPrevious());
    }
}
