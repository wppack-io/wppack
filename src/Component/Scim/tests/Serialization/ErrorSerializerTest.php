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

namespace WPPack\Component\Scim\Tests\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Exception\InvalidFilterException;
use WPPack\Component\Scim\Exception\InvalidPatchException;
use WPPack\Component\Scim\Exception\InvalidValueException;
use WPPack\Component\Scim\Exception\MutabilityException;
use WPPack\Component\Scim\Exception\ResourceConflictException;
use WPPack\Component\Scim\Exception\ResourceNotFoundException;
use WPPack\Component\Scim\Exception\ScimException;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Serialization\ErrorSerializer;

#[CoversClass(ErrorSerializer::class)]
#[CoversClass(ScimException::class)]
#[CoversClass(InvalidFilterException::class)]
#[CoversClass(InvalidPatchException::class)]
#[CoversClass(InvalidValueException::class)]
#[CoversClass(MutabilityException::class)]
#[CoversClass(ResourceConflictException::class)]
#[CoversClass(ResourceNotFoundException::class)]
final class ErrorSerializerTest extends TestCase
{
    #[Test]
    public function fromMessageEmitsScimErrorBody(): void
    {
        $result = ErrorSerializer::fromMessage('Bad input', httpStatus: 400, scimType: 'invalidValue');

        self::assertSame([ScimConstants::ERROR_SCHEMA], $result['schemas']);
        self::assertSame('400', $result['status']);
        self::assertSame('invalidValue', $result['scimType']);
        self::assertSame('Bad input', $result['detail']);
    }

    #[Test]
    public function fromMessageOmitsNullScimType(): void
    {
        $result = ErrorSerializer::fromMessage('Not found', httpStatus: 404);

        self::assertArrayNotHasKey('scimType', $result);
        self::assertSame('404', $result['status']);
        self::assertSame('Not found', $result['detail']);
    }

    #[Test]
    public function fromExceptionMatchesExceptionsOwnShape(): void
    {
        $exception = new InvalidFilterException('Bad filter');

        self::assertSame($exception->toScimError(), ErrorSerializer::fromException($exception));
    }

    #[Test]
    public function scimExceptionDefaultsTo400AndNoType(): void
    {
        $exception = new ScimException('Generic error');

        self::assertSame(400, $exception->getHttpStatus());
        self::assertNull($exception->getScimType());
        $body = $exception->toScimError();
        self::assertSame('400', $body['status']);
        self::assertArrayNotHasKey('scimType', $body, 'array_filter drops null');
    }

    #[Test]
    public function scimExceptionPropagatesCustomStatusAndType(): void
    {
        $exception = new ScimException('boom', httpStatus: 422, scimType: 'invalidValue');

        self::assertSame(422, $exception->getHttpStatus());
        self::assertSame('invalidValue', $exception->getScimType());
        self::assertSame([
            'schemas' => [ScimConstants::ERROR_SCHEMA],
            'status' => '422',
            'scimType' => 'invalidValue',
            'detail' => 'boom',
        ], $exception->toScimError());
    }

    #[Test]
    public function invalidFilterExceptionIs400InvalidFilter(): void
    {
        $exception = new InvalidFilterException();

        self::assertSame(400, $exception->getHttpStatus());
        self::assertSame('invalidFilter', $exception->getScimType());
    }

    #[Test]
    public function invalidPatchExceptionDefaultsToInvalidValue(): void
    {
        $exception = new InvalidPatchException();

        self::assertSame(400, $exception->getHttpStatus());
        self::assertSame('invalidValue', $exception->getScimType());
    }

    #[Test]
    public function invalidPatchExceptionAcceptsCustomScimType(): void
    {
        $exception = new InvalidPatchException('no target', scimType: 'noTarget');

        self::assertSame('noTarget', $exception->getScimType());
    }

    #[Test]
    public function invalidValueExceptionIs400InvalidValue(): void
    {
        $exception = new InvalidValueException();

        self::assertSame(400, $exception->getHttpStatus());
        self::assertSame('invalidValue', $exception->getScimType());
    }

    #[Test]
    public function mutabilityExceptionIs400Mutability(): void
    {
        $exception = new MutabilityException();

        self::assertSame(400, $exception->getHttpStatus());
        self::assertSame('mutability', $exception->getScimType());
    }

    #[Test]
    public function resourceConflictExceptionIs409Uniqueness(): void
    {
        $exception = new ResourceConflictException();

        self::assertSame(409, $exception->getHttpStatus());
        self::assertSame('uniqueness', $exception->getScimType());
    }

    #[Test]
    public function resourceNotFoundExceptionIs404WithoutScimType(): void
    {
        $exception = new ResourceNotFoundException();

        self::assertSame(404, $exception->getHttpStatus());
        self::assertNull($exception->getScimType());
    }

    #[Test]
    public function exceptionPreservesPreviousForChaining(): void
    {
        $root = new \RuntimeException('root cause');
        $exception = new ScimException('outer', 500, 'serverError', $root);

        self::assertSame($root, $exception->getPrevious());
    }
}
