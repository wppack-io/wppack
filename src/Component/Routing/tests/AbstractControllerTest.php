<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\BinaryFileResponse;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\Routing\AbstractController;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\TemplateResponse;

final class AbstractControllerTest extends TestCase
{
    private AbstractController $controller;

    protected function setUp(): void
    {
        $this->controller = new class extends AbstractController {
            public function callRender(
                string $template,
                array $context = [],
                int $statusCode = 200,
                array $headers = [],
            ): TemplateResponse {
                return $this->render($template, $context, $statusCode, $headers);
            }

            public function callBlock(
                string $slug,
                array $context = [],
                int $statusCode = 200,
                array $headers = [],
            ): BlockTemplateResponse {
                return $this->block($slug, $context, $statusCode, $headers);
            }

            public function callJson(
                mixed $data,
                int $statusCode = 200,
                array $headers = [],
            ): JsonResponse {
                return $this->json($data, $statusCode, $headers);
            }

            public function callRedirect(
                string $url,
                int $statusCode = 302,
                bool $safe = true,
                array $headers = [],
            ): RedirectResponse {
                return $this->redirect($url, $statusCode, $safe, $headers);
            }

            public function callFile(
                string $path,
                ?string $filename = null,
                string $disposition = 'attachment',
                array $headers = [],
            ): BinaryFileResponse {
                return $this->file($path, $filename, $disposition, $headers);
            }
        };
    }

    #[Test]
    public function renderReturnsTemplateResponse(): void
    {
        $response = $this->controller->callRender(
            '/path/to/template.php',
            ['key' => 'value'],
            201,
            ['X-Custom' => 'header'],
        );

        self::assertInstanceOf(TemplateResponse::class, $response);
        self::assertSame('/path/to/template.php', $response->template);
        self::assertSame(['key' => 'value'], $response->context);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Custom' => 'header'], $response->headers);
    }

    #[Test]
    public function blockReturnsBlockTemplateResponse(): void
    {
        $response = $this->controller->callBlock(
            'single-portfolio',
            ['slug' => 'my-project'],
            201,
            ['X-Block' => 'yes'],
        );

        self::assertInstanceOf(BlockTemplateResponse::class, $response);
        self::assertSame('single-portfolio', $response->slug);
        self::assertSame(['slug' => 'my-project'], $response->context);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Block' => 'yes'], $response->headers);
    }

    #[Test]
    public function jsonReturnsJsonResponse(): void
    {
        $data = ['products' => [['id' => 1]]];
        $response = $this->controller->callJson($data, 201, ['X-Total' => '1']);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame($data, $response->data);
        self::assertSame(201, $response->statusCode);
        self::assertArrayHasKey('X-Total', $response->headers);
    }

    #[Test]
    public function redirectReturnsRedirectResponse(): void
    {
        $response = $this->controller->callRedirect(
            'https://example.com',
            301,
            false,
            ['X-Redirect' => 'yes'],
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://example.com', $response->url);
        self::assertSame(301, $response->statusCode);
        self::assertFalse($response->safe);
        self::assertArrayHasKey('X-Redirect', $response->headers);
    }

    #[Test]
    public function fileReturnsBinaryFileResponse(): void
    {
        $response = $this->controller->callFile(
            '/path/to/file.pdf',
            'report.pdf',
            'inline',
            ['Cache-Control' => 'no-cache'],
        );

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('/path/to/file.pdf', $response->path);
        self::assertSame('report.pdf', $response->filename);
        self::assertSame('inline', $response->disposition);
        self::assertSame(['Cache-Control' => 'no-cache'], $response->headers);
    }
}
