<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Response;

final class RedirectResponseTest extends TestCase
{
    #[Test]
    public function storesUrlAndSafeFlag(): void
    {
        $response = new RedirectResponse('https://example.com', safe: false);

        self::assertSame('https://example.com', $response->url);
        self::assertFalse($response->safe);
    }

    #[Test]
    public function locationHeaderIsAutoSet(): void
    {
        $response = new RedirectResponse('https://example.com/redirect');

        self::assertArrayHasKey('Location', $response->headers);
        self::assertSame('https://example.com/redirect', $response->headers['Location']);
    }

    #[Test]
    public function defaultStatusIs302AndSafeIsTrue(): void
    {
        $response = new RedirectResponse('https://example.com');

        self::assertSame(302, $response->statusCode);
        self::assertTrue($response->safe);
    }

    #[Test]
    public function customStatusCode(): void
    {
        $response = new RedirectResponse('https://example.com', 301);

        self::assertSame(301, $response->statusCode);
    }

    #[Test]
    public function extendsResponse(): void
    {
        $response = new RedirectResponse('https://example.com');

        self::assertInstanceOf(Response::class, $response);
    }
}
