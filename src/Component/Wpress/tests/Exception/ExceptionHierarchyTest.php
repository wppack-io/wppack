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

namespace WPPack\Component\Wpress\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Wpress\Exception\ArchiveException;
use WPPack\Component\Wpress\Exception\EncryptionException;
use WPPack\Component\Wpress\Exception\EntryNotFoundException;
use WPPack\Component\Wpress\Exception\ExceptionInterface;
use WPPack\Component\Wpress\Exception\InvalidArgumentException;

#[CoversClass(ArchiveException::class)]
#[CoversClass(EncryptionException::class)]
#[CoversClass(EntryNotFoundException::class)]
#[CoversClass(InvalidArgumentException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function runtimeDescendantsImplementMarker(): void
    {
        foreach ([ArchiveException::class, EncryptionException::class, EntryNotFoundException::class] as $class) {
            $e = new $class('boom');
            self::assertInstanceOf(\RuntimeException::class, $e);
            self::assertInstanceOf(ExceptionInterface::class, $e);
            self::assertSame('boom', $e->getMessage());
        }
    }

    #[Test]
    public function invalidArgumentExtendsCoreAndImplementsMarker(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
    }
}
