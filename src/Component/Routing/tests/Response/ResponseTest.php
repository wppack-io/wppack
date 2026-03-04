<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Response\BinaryFileResponse;
use WpPack\Component\Routing\Response\JsonResponse;
use WpPack\Component\Routing\Response\RedirectResponse;
use WpPack\Component\Routing\Response\Response;
use WpPack\Component\Routing\Response\RouteResponse;

final class ResponseTest extends TestCase
{
    #[Test]
    public function responseStoresContentStatusAndHeaders(): void
    {
        $response = new Response('<h1>Hello</h1>', 201, ['X-Custom' => 'value']);

        self::assertSame('<h1>Hello</h1>', $response->content);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Custom' => 'value'], $response->headers);
    }

    #[Test]
    public function responseDefaultValues(): void
    {
        $response = new Response();

        self::assertSame('', $response->content);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function responseInheritsFromRouteResponse(): void
    {
        $response = new Response();

        self::assertInstanceOf(RouteResponse::class, $response);
    }

    #[Test]
    public function jsonResponseStoresDataStatusAndHeaders(): void
    {
        $data = ['products' => [['id' => 1, 'name' => 'Widget']]];
        $response = new JsonResponse($data, 201, ['X-Total' => '1']);

        self::assertSame($data, $response->data);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Total' => '1'], $response->headers);
        self::assertInstanceOf(RouteResponse::class, $response);
    }

    #[Test]
    public function jsonResponseDefaultValues(): void
    {
        $response = new JsonResponse(null);

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function redirectResponseStoresUrlStatusSafeAndHeaders(): void
    {
        $response = new RedirectResponse('https://example.com', 301, false, ['X-Redirect' => 'yes']);

        self::assertSame('https://example.com', $response->url);
        self::assertSame(301, $response->statusCode);
        self::assertFalse($response->safe);
        self::assertSame(['X-Redirect' => 'yes'], $response->headers);
        self::assertInstanceOf(RouteResponse::class, $response);
    }

    #[Test]
    public function redirectResponseDefaults(): void
    {
        $response = new RedirectResponse('/new-location');

        self::assertSame('/new-location', $response->url);
        self::assertSame(302, $response->statusCode);
        self::assertTrue($response->safe);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function binaryFileResponseStoresPathFilenameDispositionAndHeaders(): void
    {
        $response = new BinaryFileResponse(
            '/path/to/file.pdf',
            'report.pdf',
            'inline',
            201,
            ['Cache-Control' => 'no-cache'],
        );

        self::assertSame('/path/to/file.pdf', $response->path);
        self::assertSame('report.pdf', $response->filename);
        self::assertSame('inline', $response->disposition);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['Cache-Control' => 'no-cache'], $response->headers);
        self::assertInstanceOf(RouteResponse::class, $response);
    }

    #[Test]
    public function binaryFileResponseDefaults(): void
    {
        $response = new BinaryFileResponse('/path/to/file.zip');

        self::assertSame('/path/to/file.zip', $response->path);
        self::assertNull($response->filename);
        self::assertSame('attachment', $response->disposition);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }
}
