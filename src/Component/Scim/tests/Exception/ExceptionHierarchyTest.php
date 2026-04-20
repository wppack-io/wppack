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

namespace WPPack\Component\Scim\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Exception\ExceptionInterface;
use WPPack\Component\Scim\Exception\InvalidFilterException;
use WPPack\Component\Scim\Exception\InvalidPatchException;
use WPPack\Component\Scim\Exception\InvalidValueException;
use WPPack\Component\Scim\Exception\MutabilityException;
use WPPack\Component\Scim\Exception\ResourceConflictException;
use WPPack\Component\Scim\Exception\ResourceNotFoundException;
use WPPack\Component\Scim\Exception\ScimException;
use WPPack\Component\Scim\Schema\ScimConstants;

#[CoversClass(ScimException::class)]
#[CoversClass(InvalidFilterException::class)]
#[CoversClass(InvalidPatchException::class)]
#[CoversClass(InvalidValueException::class)]
#[CoversClass(MutabilityException::class)]
#[CoversClass(ResourceConflictException::class)]
#[CoversClass(ResourceNotFoundException::class)]
final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function scimExceptionCarriesStatusAndTypeAndFormatsToScimError(): void
    {
        $e = new ScimException('boom', httpStatus: 418, scimType: 'tooSpicy');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('boom', $e->getMessage());
        self::assertSame(418, $e->getHttpStatus());
        self::assertSame('tooSpicy', $e->getScimType());

        $error = $e->toScimError();
        self::assertSame([ScimConstants::ERROR_SCHEMA], $error['schemas']);
        self::assertSame('418', $error['status']);
        self::assertSame('tooSpicy', $error['scimType']);
        self::assertSame('boom', $error['detail']);
    }

    #[Test]
    public function toScimErrorOmitsScimTypeWhenNull(): void
    {
        $e = new ScimException('missing', httpStatus: 404);
        $error = $e->toScimError();

        self::assertArrayNotHasKey('scimType', $error);
        self::assertSame('404', $error['status']);
    }

    #[Test]
    public function toScimErrorPreservesStatusAsString(): void
    {
        $e = new ScimException('boom', httpStatus: 500);
        self::assertSame('500', $e->toScimError()['status']);
    }

    #[Test]
    public function previousExceptionChains(): void
    {
        $previous = new \LogicException('inner');
        $e = new ScimException('outer', previous: $previous);

        self::assertSame($previous, $e->getPrevious());
    }

    /**
     * @return iterable<string, array{class-string<ScimException>, int, string}>
     */
    public static function subclassProvider(): iterable
    {
        yield 'InvalidFilter' => [InvalidFilterException::class, 400, 'invalidFilter'];
        yield 'InvalidValue' => [InvalidValueException::class, 400, 'invalidValue'];
        yield 'Mutability' => [MutabilityException::class, 400, 'mutability'];
        yield 'ResourceConflict' => [ResourceConflictException::class, 409, 'uniqueness'];
    }

    /**
     * @param class-string<ScimException> $class
     */
    #[Test]
    #[DataProvider('subclassProvider')]
    public function subclassDefaultsHaveCorrectStatusAndScimType(string $class, int $expectedStatus, string $expectedScimType): void
    {
        $e = new $class();

        self::assertSame($expectedStatus, $e->getHttpStatus());
        self::assertSame($expectedScimType, $e->getScimType());
    }

    #[Test]
    public function invalidPatchExceptionAllowsCustomScimType(): void
    {
        $e = new InvalidPatchException('path invalid', scimType: 'invalidPath');

        self::assertSame(400, $e->getHttpStatus());
        self::assertSame('invalidPath', $e->getScimType());
    }

    #[Test]
    public function invalidPatchDefaultsToInvalidValue(): void
    {
        $e = new InvalidPatchException();

        self::assertSame('invalidValue', $e->getScimType());
    }

    #[Test]
    public function resourceNotFoundExceptionHas404AndNoScimType(): void
    {
        $e = new ResourceNotFoundException('user not found');

        self::assertSame(404, $e->getHttpStatus());
        self::assertNull($e->getScimType());
    }
}
