<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Rest\AbstractRestController;

final class AbstractRestControllerTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        $this->controller = new class extends AbstractRestController {
            public function callJson(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
            {
                return $this->json($data, $statusCode, $headers);
            }

            public function callCreated(mixed $data = null, array $headers = []): JsonResponse
            {
                return $this->created($data, $headers);
            }

            public function callNoContent(array $headers = []): Response
            {
                return $this->noContent($headers);
            }

            public function callResponse(mixed $data = null, int $statusCode = 200, array $headers = []): Response
            {
                return $this->response($data, $statusCode, $headers);
            }
        };
    }

    #[Test]
    public function jsonReturnsJsonResponse(): void
    {
        $response = $this->controller->callJson(['key' => 'value'], 200, ['X-Test' => 'yes']);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(['key' => 'value'], $response->data);
        self::assertSame(200, $response->statusCode);
        self::assertArrayHasKey('X-Test', $response->headers);
        self::assertSame('yes', $response->headers['X-Test']);
    }

    #[Test]
    public function jsonDefaultValues(): void
    {
        $response = $this->controller->callJson(null);

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function createdReturns201(): void
    {
        $response = $this->controller->callCreated(['id' => 1]);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(['id' => 1], $response->data);
        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function noContentReturns204(): void
    {
        $response = $this->controller->callNoContent();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('', $response->content);
        self::assertSame(204, $response->statusCode);
    }

    #[Test]
    public function responseReturnsResponse(): void
    {
        $response = $this->controller->callResponse('data', 202, ['X-Header' => 'val']);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('data', $response->content);
        self::assertSame(202, $response->statusCode);
        self::assertSame(['X-Header' => 'val'], $response->headers);
    }
}
