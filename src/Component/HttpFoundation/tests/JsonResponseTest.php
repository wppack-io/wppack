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

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Response;

final class JsonResponseTest extends TestCase
{
    #[Test]
    public function jsonEncodesData(): void
    {
        $data = ['key' => 'value', 'number' => 42];
        $response = new JsonResponse($data);

        self::assertSame('{"key":"value","number":42}', $response->content);
    }

    #[Test]
    public function contentTypeHeaderIsAutoSet(): void
    {
        $response = new JsonResponse(['test' => true]);

        self::assertArrayHasKey('Content-Type', $response->headers);
        self::assertSame('application/json', $response->headers['Content-Type']);
    }

    #[Test]
    public function dataReadonlyPropertyHoldsOriginalData(): void
    {
        $data = ['name' => 'John', 'age' => 30];
        $response = new JsonResponse($data);

        self::assertSame($data, $response->data);
    }

    #[Test]
    public function contentHoldsJsonString(): void
    {
        $response = new JsonResponse(['foo' => 'bar']);

        self::assertIsString($response->content);
        self::assertSame(['foo' => 'bar'], json_decode($response->content, true));
    }

    #[Test]
    public function defaultStatusCode200(): void
    {
        $response = new JsonResponse();

        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function customStatusCode(): void
    {
        $response = new JsonResponse(['error' => 'not found'], 404);

        self::assertSame(404, $response->statusCode);
    }

    #[Test]
    public function customHeadersAreMergedWithContentType(): void
    {
        $response = new JsonResponse(
            data: ['test' => true],
            headers: ['X-Custom' => 'value'],
        );

        self::assertSame('application/json', $response->headers['Content-Type']);
        self::assertSame('value', $response->headers['X-Custom']);
    }

    #[Test]
    public function extendsResponse(): void
    {
        $response = new JsonResponse();

        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function throwsOnJsonEncodeFailure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Failed to encode data to JSON');

        // NAN without JSON_THROW_ON_ERROR triggers the $json === false path
        new JsonResponse(data: \NAN, encodingOptions: 0);
    }
}
