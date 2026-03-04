<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Response;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\RouteResponse;
use WpPack\Component\Routing\Response\TemplateResponse;

final class TemplateResponseTest extends TestCase
{
    #[Test]
    public function templateResponseStoresTemplateContextStatusAndHeaders(): void
    {
        $response = new TemplateResponse(
            '/path/to/template.php',
            ['product' => 'Widget'],
            201,
            ['X-Custom' => 'value'],
        );

        self::assertSame('/path/to/template.php', $response->template);
        self::assertSame(['product' => 'Widget'], $response->context);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Custom' => 'value'], $response->headers);
        self::assertInstanceOf(RouteResponse::class, $response);
    }

    #[Test]
    public function templateResponseDefaultValues(): void
    {
        $response = new TemplateResponse('/path/to/template.php');

        self::assertSame('/path/to/template.php', $response->template);
        self::assertSame([], $response->context);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function blockTemplateResponseStoresSlugContextStatusAndHeaders(): void
    {
        $response = new BlockTemplateResponse(
            'single-portfolio',
            ['portfolio_slug' => 'my-project'],
            201,
            ['X-Block' => 'yes'],
        );

        self::assertSame('single-portfolio', $response->slug);
        self::assertSame(['portfolio_slug' => 'my-project'], $response->context);
        self::assertSame(201, $response->statusCode);
        self::assertSame(['X-Block' => 'yes'], $response->headers);
        self::assertInstanceOf(RouteResponse::class, $response);
    }

    #[Test]
    public function blockTemplateResponseDefaultValues(): void
    {
        $response = new BlockTemplateResponse('single-portfolio');

        self::assertSame('single-portfolio', $response->slug);
        self::assertSame([], $response->context);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }
}
