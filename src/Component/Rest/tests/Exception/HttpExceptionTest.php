<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\Exception;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Exception\BadRequestException;
use WpPack\Component\Rest\Exception\ConflictException;
use WpPack\Component\Rest\Exception\ExceptionInterface;
use WpPack\Component\Rest\Exception\ForbiddenException;
use WpPack\Component\Rest\Exception\HttpException;
use WpPack\Component\Rest\Exception\MethodNotAllowedException;
use WpPack\Component\Rest\Exception\NotFoundException;
use WpPack\Component\Rest\Exception\UnauthorizedException;
use WpPack\Component\Rest\Exception\UnprocessableEntityException;

final class HttpExceptionTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $exception = new HttpException();

        self::assertSame('', $exception->getMessage());
        self::assertSame(500, $exception->getStatusCode());
        self::assertSame('rest_error', $exception->getErrorCode());
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
    public function getStatusCode(): void
    {
        $exception = new HttpException('Test', 404);

        self::assertSame(404, $exception->getStatusCode());
    }

    #[Test]
    public function getErrorCode(): void
    {
        $exception = new HttpException('Test', errorCode: 'my_error');

        self::assertSame('my_error', $exception->getErrorCode());
    }

    #[Test]
    public function badRequestException(): void
    {
        $exception = new BadRequestException();

        self::assertSame(400, $exception->getStatusCode());
        self::assertSame('rest_bad_request', $exception->getErrorCode());
        self::assertSame('Bad request.', $exception->getMessage());
        self::assertInstanceOf(HttpException::class, $exception);
        self::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    #[Test]
    public function unauthorizedException(): void
    {
        $exception = new UnauthorizedException();

        self::assertSame(401, $exception->getStatusCode());
        self::assertSame('rest_unauthorized', $exception->getErrorCode());
        self::assertSame('Unauthorized.', $exception->getMessage());
    }

    #[Test]
    public function forbiddenException(): void
    {
        $exception = new ForbiddenException();

        self::assertSame(403, $exception->getStatusCode());
        self::assertSame('rest_forbidden', $exception->getErrorCode());
        self::assertSame('Forbidden.', $exception->getMessage());
    }

    #[Test]
    public function notFoundException(): void
    {
        $exception = new NotFoundException();

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('rest_not_found', $exception->getErrorCode());
        self::assertSame('Not found.', $exception->getMessage());
    }

    #[Test]
    public function conflictException(): void
    {
        $exception = new ConflictException();

        self::assertSame(409, $exception->getStatusCode());
        self::assertSame('rest_conflict', $exception->getErrorCode());
        self::assertSame('Conflict.', $exception->getMessage());
    }

    #[Test]
    public function unprocessableEntityException(): void
    {
        $exception = new UnprocessableEntityException();

        self::assertSame(422, $exception->getStatusCode());
        self::assertSame('rest_unprocessable_entity', $exception->getErrorCode());
        self::assertSame('Unprocessable entity.', $exception->getMessage());
    }
}
