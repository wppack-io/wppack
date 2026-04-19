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

namespace WPPack\Component\HttpClient\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpClient\Exception\RequestException;
use WPPack\Component\HttpClient\Request;
use WPPack\Component\HttpClient\Response;

#[CoversClass(RequestException::class)]
final class RequestExceptionTest extends TestCase
{
    #[Test]
    public function messageContainsStatusCode(): void
    {
        $response = new Response(statusCode: 404);
        $exception = new RequestException($response);

        self::assertStringContainsString('404', $exception->getMessage());
    }

    #[Test]
    public function storesResponse(): void
    {
        $response = new Response(statusCode: 500);
        $exception = new RequestException($response);

        self::assertSame($response, $exception->response);
    }

    #[Test]
    public function getRequestReturnsRequestWhenProvided(): void
    {
        $response = new Response(statusCode: 422);
        $request = new Request('POST', 'https://example.com/api');
        $exception = new RequestException($response, $request);

        self::assertSame($request, $exception->getRequest());
    }

    #[Test]
    public function getRequestReturnsNullByDefault(): void
    {
        $response = new Response(statusCode: 500);
        $exception = new RequestException($response);

        self::assertNull($exception->getRequest());
    }
}
