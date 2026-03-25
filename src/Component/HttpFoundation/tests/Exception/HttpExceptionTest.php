<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Exception\BadRequestException;
use WpPack\Component\HttpFoundation\Exception\ConflictException;
use WpPack\Component\HttpFoundation\Exception\ExceptionInterface;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;
use WpPack\Component\HttpFoundation\Exception\HttpException;
use WpPack\Component\HttpFoundation\Exception\MethodNotAllowedException;
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\Exception\UnauthorizedException;
use WpPack\Component\HttpFoundation\Exception\UnprocessableEntityException;

final class HttpExceptionTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $exception = new HttpException();

        self::assertSame('', $exception->getMessage());
        self::assertSame(500, $exception->getStatusCode());
        self::assertSame('http_error', $exception->getErrorCode());
        self::assertNull($exception->getPrevious());
    }

    #[Test]
    public function customValues(): void
    {
        $previous = new \RuntimeException('Previous');
        $exception = new HttpException('Custom error', 418, 'custom_code', $previous);

        self::assertSame('Custom error', $exception->getMessage());
        self::assertSame(418, $exception->getStatusCode());
        self::assertSame('custom_code', $exception->getErrorCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function implementsExceptionInterface(): void
    {
        $exception = new HttpException();

        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function badRequestException(): void
    {
        $exception = new BadRequestException();

        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('http_bad_request', $exception->getErrorCode());
        self::assertSame('Bad request.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function badRequestExceptionWithCustomErrorCode(): void
    {
        $exception = new BadRequestException('Invalid input', 'validation_error');

        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('validation_error', $exception->getErrorCode());
        self::assertSame('Invalid input', $exception->getMessage());
    }

    #[Test]
    public function unauthorizedException(): void
    {
        $exception = new UnauthorizedException();

        self::assertSame(401, $exception->getStatusCode());
        self::assertSame('http_unauthorized', $exception->getErrorCode());
        self::assertSame('Unauthorized.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function unauthorizedExceptionWithCustomErrorCode(): void
    {
        $exception = new UnauthorizedException('Token expired', 'token_expired');

        self::assertSame(401, $exception->getStatusCode());
        self::assertSame('token_expired', $exception->getErrorCode());
    }

    #[Test]
    public function forbiddenException(): void
    {
        $exception = new ForbiddenException();

        self::assertSame(403, $exception->getStatusCode());
        self::assertSame('http_forbidden', $exception->getErrorCode());
        self::assertSame('Forbidden.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function forbiddenExceptionWithCustomErrorCode(): void
    {
        $exception = new ForbiddenException('Access denied', 'access_denied');

        self::assertSame(403, $exception->getStatusCode());
        self::assertSame('access_denied', $exception->getErrorCode());
    }

    #[Test]
    public function notFoundException(): void
    {
        $exception = new NotFoundException();

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('http_not_found', $exception->getErrorCode());
        self::assertSame('Not found.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function notFoundExceptionWithCustomErrorCode(): void
    {
        $exception = new NotFoundException('Resource not found', 'resource_not_found');

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('resource_not_found', $exception->getErrorCode());
    }

    #[Test]
    public function methodNotAllowedException(): void
    {
        $exception = new MethodNotAllowedException();

        self::assertSame(405, $exception->getStatusCode());
        self::assertSame('http_method_not_allowed', $exception->getErrorCode());
        self::assertSame('Method not allowed.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function methodNotAllowedExceptionWithCustomErrorCode(): void
    {
        $exception = new MethodNotAllowedException('POST not supported', 'post_not_allowed');

        self::assertSame(405, $exception->getStatusCode());
        self::assertSame('post_not_allowed', $exception->getErrorCode());
    }

    #[Test]
    public function conflictException(): void
    {
        $exception = new ConflictException();

        self::assertSame(409, $exception->getStatusCode());
        self::assertSame('http_conflict', $exception->getErrorCode());
        self::assertSame('Conflict.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function conflictExceptionWithCustomErrorCode(): void
    {
        $exception = new ConflictException('Duplicate entry', 'duplicate_entry');

        self::assertSame(409, $exception->getStatusCode());
        self::assertSame('duplicate_entry', $exception->getErrorCode());
    }

    #[Test]
    public function unprocessableEntityException(): void
    {
        $exception = new UnprocessableEntityException();

        self::assertSame(422, $exception->getStatusCode());
        self::assertSame('http_unprocessable_entity', $exception->getErrorCode());
        self::assertSame('Unprocessable entity.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function unprocessableEntityExceptionWithCustomErrorCode(): void
    {
        $exception = new UnprocessableEntityException('Invalid data', 'invalid_payload');

        self::assertSame(422, $exception->getStatusCode());
        self::assertSame('invalid_payload', $exception->getErrorCode());
    }
}
