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

namespace WPPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\ServerBag;

final class ServerBagTest extends TestCase
{
    #[Test]
    public function getHeadersConvertsHttpHostToHost(): void
    {
        $bag = new ServerBag(['HTTP_HOST' => 'example.com']);
        $headers = $bag->getHeaders();

        self::assertArrayHasKey('host', $headers);
        self::assertSame('example.com', $headers['host']);
    }

    #[Test]
    public function getHeadersConvertsHttpAcceptToAccept(): void
    {
        $bag = new ServerBag(['HTTP_ACCEPT' => 'text/html']);
        $headers = $bag->getHeaders();

        self::assertArrayHasKey('accept', $headers);
        self::assertSame('text/html', $headers['accept']);
    }

    #[Test]
    public function getHeadersIncludesContentType(): void
    {
        $bag = new ServerBag(['CONTENT_TYPE' => 'application/json']);
        $headers = $bag->getHeaders();

        self::assertArrayHasKey('content-type', $headers);
        self::assertSame('application/json', $headers['content-type']);
    }

    #[Test]
    public function getHeadersIncludesContentLength(): void
    {
        $bag = new ServerBag(['CONTENT_LENGTH' => '1024']);
        $headers = $bag->getHeaders();

        self::assertArrayHasKey('content-length', $headers);
        self::assertSame('1024', $headers['content-length']);
    }

    #[Test]
    public function getHeadersIgnoresNonHttpKeys(): void
    {
        $bag = new ServerBag([
            'SERVER_NAME' => 'example.com',
            'REQUEST_METHOD' => 'GET',
            'DOCUMENT_ROOT' => '/var/www',
        ]);
        $headers = $bag->getHeaders();

        self::assertEmpty($headers);
    }

    #[Test]
    public function getHeadersCombinesHttpAndContentHeaders(): void
    {
        $bag = new ServerBag([
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/json',
            'CONTENT_LENGTH' => '512',
            'SERVER_NAME' => 'example.com',
        ]);
        $headers = $bag->getHeaders();

        self::assertCount(4, $headers);
        self::assertSame('example.com', $headers['host']);
        self::assertSame('text/html', $headers['accept']);
        self::assertSame('application/json', $headers['content-type']);
        self::assertSame('512', $headers['content-length']);
    }
}
