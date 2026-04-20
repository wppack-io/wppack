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

namespace WPPack\Component\HttpFoundation\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\Exception\BadRequestException;
use WPPack\Component\HttpFoundation\Exception\ConflictException;
use WPPack\Component\HttpFoundation\Exception\ExceptionInterface;
use WPPack\Component\HttpFoundation\Exception\ForbiddenException;
use WPPack\Component\HttpFoundation\Exception\HttpException;
use WPPack\Component\HttpFoundation\Exception\MethodNotAllowedException;
use WPPack\Component\HttpFoundation\Exception\NotFoundException;
use WPPack\Component\HttpFoundation\Exception\UnauthorizedException;
use WPPack\Component\HttpFoundation\Exception\UnprocessableEntityException;

#[CoversClass(BadRequestException::class)]
#[CoversClass(ConflictException::class)]
#[CoversClass(ForbiddenException::class)]
#[CoversClass(MethodNotAllowedException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(UnauthorizedException::class)]
#[CoversClass(UnprocessableEntityException::class)]
final class HttpExceptionSubclassesTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<HttpException>, int, string, string}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'BadRequest' => [BadRequestException::class, 400, 'Bad request.', 'http_bad_request'];
        yield 'Unauthorized' => [UnauthorizedException::class, 401, 'Unauthorized.', 'http_unauthorized'];
        yield 'Forbidden' => [ForbiddenException::class, 403, 'Forbidden.', 'http_forbidden'];
        yield 'NotFound' => [NotFoundException::class, 404, 'Not found.', 'http_not_found'];
        yield 'MethodNotAllowed' => [MethodNotAllowedException::class, 405, 'Method not allowed.', 'http_method_not_allowed'];
        yield 'Conflict' => [ConflictException::class, 409, 'Conflict.', 'http_conflict'];
        yield 'UnprocessableEntity' => [UnprocessableEntityException::class, 422, 'Unprocessable entity.', 'http_unprocessable_entity'];
    }

    /**
     * @param class-string<HttpException> $class
     */
    #[Test]
    #[DataProvider('exceptionProvider')]
    public function exceptionDefaultsCarryExpectedStatusCode(string $class, int $expectedStatus, string $defaultMessage, string $defaultErrorCode): void
    {
        $e = new $class();

        self::assertInstanceOf(HttpException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame($expectedStatus, $e->getStatusCode());
        self::assertSame($defaultMessage, $e->getMessage());
        self::assertSame($defaultErrorCode, $e->getErrorCode());
    }

    /**
     * @param class-string<HttpException> $class
     */
    #[Test]
    #[DataProvider('exceptionProvider')]
    public function exceptionCustomMessageIsPreserved(string $class, int $expectedStatus): void
    {
        $previous = new \RuntimeException('cause');
        $e = new $class('Custom message.', 'custom_code', $previous);

        self::assertSame('Custom message.', $e->getMessage());
        self::assertSame('custom_code', $e->getErrorCode());
        self::assertSame($expectedStatus, $e->getStatusCode());
        self::assertSame($previous, $e->getPrevious());
    }
}
