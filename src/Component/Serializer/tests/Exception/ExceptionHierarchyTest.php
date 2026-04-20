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

namespace WPPack\Component\Serializer\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Serializer\Exception\ExceptionInterface;
use WPPack\Component\Serializer\Exception\InvalidArgumentException;
use WPPack\Component\Serializer\Exception\NotEncodableValueException;
use WPPack\Component\Serializer\Exception\NotNormalizableValueException;

#[CoversClass(InvalidArgumentException::class)]
#[CoversClass(NotEncodableValueException::class)]
#[CoversClass(NotNormalizableValueException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function invalidArgumentExtendsCoreAndImplementsMarker(): void
    {
        $e = new InvalidArgumentException('bad');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('bad', $e->getMessage());
    }

    #[Test]
    public function notEncodableValueExtendsRuntimeAndImplementsMarker(): void
    {
        $e = new NotEncodableValueException('unencodable');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('unencodable', $e->getMessage());
    }

    #[Test]
    public function notNormalizableValueExtendsRuntimeAndImplementsMarker(): void
    {
        $e = new NotNormalizableValueException('unnormalisable');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('unnormalisable', $e->getMessage());
    }

    #[Test]
    public function allExceptionsShareMarkerInterface(): void
    {
        foreach ([InvalidArgumentException::class, NotEncodableValueException::class, NotNormalizableValueException::class] as $class) {
            self::assertContains(ExceptionInterface::class, class_implements($class), "{$class} must implement ExceptionInterface");
        }
    }
}
