<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Ajax\Response\JsonResponse;

final class JsonResponseTest extends TestCase
{
    #[Test]
    public function successFactoryCreatesSuccessResponse(): void
    {
        $response = JsonResponse::success(['key' => 'value']);

        self::assertSame(['key' => 'value'], $response->data);
        self::assertTrue($response->success);
        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function successFactoryWithCustomStatusCode(): void
    {
        $response = JsonResponse::success(['key' => 'value'], 201);

        self::assertTrue($response->success);
        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function successFactoryWithNullData(): void
    {
        $response = JsonResponse::success();

        self::assertNull($response->data);
        self::assertTrue($response->success);
    }

    #[Test]
    public function errorFactoryCreatesErrorResponse(): void
    {
        $response = JsonResponse::error('Something went wrong');

        self::assertSame('Something went wrong', $response->data);
        self::assertFalse($response->success);
        self::assertSame(400, $response->statusCode);
    }

    #[Test]
    public function errorFactoryWithCustomStatusCode(): void
    {
        $response = JsonResponse::error('Not found', 404);

        self::assertFalse($response->success);
        self::assertSame(404, $response->statusCode);
    }

    #[Test]
    public function errorFactoryWithNullData(): void
    {
        $response = JsonResponse::error();

        self::assertNull($response->data);
        self::assertFalse($response->success);
    }

    #[Test]
    public function constructorSetsProperties(): void
    {
        $response = new JsonResponse(data: ['test'], success: true, statusCode: 201);

        self::assertSame(['test'], $response->data);
        self::assertTrue($response->success);
        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function constructorDefaultStatusCode(): void
    {
        $response = new JsonResponse(data: null, success: false);

        self::assertSame(200, $response->statusCode);
    }
}
