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
use WPPack\Component\Routing\Response\BlockTemplateResponse;

final class ResponseClassesTest extends TestCase
{
    #[Test]
    public function jsonResponseHoldsData(): void
    {
        $data = ['key' => 'value', 'nested' => ['a' => 1]];
        $response = new JsonResponse($data, 201, ['Content-Type' => 'application/json']);

        self::assertSame($data, $response->data);
        self::assertSame(201, $response->statusCode);
    }

    #[Test]
    public function redirectResponseHoldsUrlAndSafeFlag(): void
    {
        $response = new RedirectResponse('https://example.com/redirect');

        self::assertSame('https://example.com/redirect', $response->url);
        self::assertTrue($response->safe);
        self::assertSame(302, $response->statusCode);
    }

    #[Test]
    public function blockTemplateResponseHoldsSlugAndContext(): void
    {
        $context = ['title' => 'Hello', 'items' => [1, 2, 3]];
        $response = new BlockTemplateResponse('custom-template', $context);

        self::assertSame('custom-template', $response->slug);
        self::assertSame($context, $response->context);
    }

    #[Test]
    public function binaryFileResponseHoldsPathAndDisposition(): void
    {
        $response = new BinaryFileResponse('/var/files/report.pdf');

        self::assertSame('/var/files/report.pdf', $response->path);
        self::assertSame('attachment', $response->disposition);
    }

    #[Test]
    public function blockTemplateResponseDefaultValues(): void
    {
        $response = new BlockTemplateResponse('archive');

        self::assertSame('archive', $response->slug);
        self::assertSame([], $response->context);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function blockTemplateResponseContentIsEmpty(): void
    {
        $response = new BlockTemplateResponse('single', ['key' => 'value']);

        // BlockTemplateResponse passes empty string to parent Response
        self::assertSame('', $response->content);
    }

    #[Test]
    public function blockTemplateResponseWithCustomStatusCode(): void
    {
        $response = new BlockTemplateResponse('404', ['error' => 'Not Found'], 404);

        self::assertSame('404', $response->slug);
        self::assertSame(404, $response->statusCode);
        self::assertSame(['error' => 'Not Found'], $response->context);
    }

    #[Test]
    public function blockTemplateResponseWithHeaders(): void
    {
        $response = new BlockTemplateResponse(
            'custom',
            [],
            200,
            ['X-Cache' => 'MISS', 'Vary' => 'Accept'],
        );

        self::assertSame(['X-Cache' => 'MISS', 'Vary' => 'Accept'], $response->headers);
    }

    #[Test]
    public function blockTemplateResponseExtendsResponse(): void
    {
        $response = new BlockTemplateResponse('page');

        self::assertInstanceOf(\WPPack\Component\HttpFoundation\Response::class, $response);
    }

    #[Test]
    public function jsonResponseWithNullData(): void
    {
        $response = new JsonResponse(null);

        self::assertNull($response->data);
        self::assertSame(200, $response->statusCode);
    }

    #[Test]
    public function redirectResponseWithUnsafe(): void
    {
        $response = new RedirectResponse('https://external.com', 301, false);

        self::assertFalse($response->safe);
        self::assertSame(301, $response->statusCode);
    }

    #[Test]
    public function binaryFileResponseWithInlineDisposition(): void
    {
        $response = new BinaryFileResponse('/path/image.jpg', 'photo.jpg', 'inline');

        self::assertSame('inline', $response->disposition);
        self::assertSame('photo.jpg', $response->filename);
    }
}
