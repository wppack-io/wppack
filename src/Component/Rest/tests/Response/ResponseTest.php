<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Response\Response;
use WpPack\Component\Rest\Response\RestResponse;

final class ResponseTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $response = new Response();

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function customValues(): void
    {
        $response = new Response(
            ['key' => 'value'],
            201,
            ['X-Custom' => 'header'],
        );

        self::assertSame(['key' => 'value'], $response->data);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Custom' => 'header'], $response->headers);
    }

    #[Test]
    public function extendsRestResponse(): void
    {
        self::assertInstanceOf(RestResponse::class, new Response());
    }

    #[Test]
    public function isFinalClass(): void
    {
        $reflection = new \ReflectionClass(Response::class);

        self::assertTrue($reflection->isFinal());
    }
}
