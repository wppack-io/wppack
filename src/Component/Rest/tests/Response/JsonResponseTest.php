<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Response\JsonResponse;
use WpPack\Component\Rest\Response\RestResponse;

final class JsonResponseTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $response = new JsonResponse();

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function customValues(): void
    {
        $response = new JsonResponse(
            ['items' => [1, 2, 3]],
            201,
            ['Content-Type' => 'application/json'],
        );

        self::assertSame(['items' => [1, 2, 3]], $response->data);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['Content-Type' => 'application/json'], $response->headers);
    }

    #[Test]
    public function extendsRestResponse(): void
    {
        self::assertInstanceOf(RestResponse::class, new JsonResponse());
    }

    #[Test]
    public function isFinalClass(): void
    {
        $reflection = new \ReflectionClass(JsonResponse::class);

        self::assertTrue($reflection->isFinal());
    }
}
