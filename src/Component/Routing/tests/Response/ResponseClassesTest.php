<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\BinaryFileResponse;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\Routing\Response\BlockTemplateResponse;

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
}
