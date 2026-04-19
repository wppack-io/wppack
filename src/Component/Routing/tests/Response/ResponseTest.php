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

namespace WPPack\Component\Routing\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\BinaryFileResponse;
use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\RedirectResponse;
use WPPack\Component\HttpFoundation\Response;

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
    public function jsonResponseStoresDataStatusAndHeaders(): void
    {
        $data = ['products' => [['id' => 1, 'name' => 'Widget']]];
        $response = new JsonResponse($data, 201, ['X-Total' => '1']);

        self::assertSame($data, $response->data);
        self::assertSame(201, $response->statusCode);
        self::assertArrayHasKey('X-Total', $response->headers);
        self::assertSame('1', $response->headers['X-Total']);
        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function jsonResponseDefaultValues(): void
    {
        $response = new JsonResponse(null);

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function redirectResponseStoresUrlStatusSafeAndHeaders(): void
    {
        $response = new RedirectResponse('https://example.com', 301, false, ['X-Redirect' => 'yes']);

        self::assertSame('https://example.com', $response->url);
        self::assertSame(301, $response->statusCode);
        self::assertFalse($response->safe);
        self::assertArrayHasKey('X-Redirect', $response->headers);
        self::assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function redirectResponseDefaults(): void
    {
        $response = new RedirectResponse('/new-location');

        self::assertSame('/new-location', $response->url);
        self::assertSame(302, $response->statusCode);
        self::assertTrue($response->safe);
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
        self::assertInstanceOf(Response::class, $response);
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
