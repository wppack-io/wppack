<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Response;

final class ResponseTest extends TestCase
{
    #[Test]
    public function defaultConstructorValues(): void
    {
        $response = new Response();

        self::assertSame('', $response->content);
        self::assertSame(200, $response->statusCode);
        self::assertSame([], $response->headers);
    }

    #[Test]
    public function customValues(): void
    {
        $headers = ['Content-Type' => 'text/html', 'X-Custom' => 'value'];
        $response = new Response('<h1>Hello</h1>', 201, $headers);

        self::assertSame('<h1>Hello</h1>', $response->content);
        self::assertSame(201, $response->statusCode);
        self::assertSame($headers, $response->headers);
    }

    #[Test]
    public function readonlyProperties(): void
    {
        $response = new Response('body', 200, ['X-Test' => '1']);
        $reflection = new \ReflectionClass($response);

        self::assertTrue($reflection->getProperty('content')->isReadOnly());
        self::assertTrue($reflection->getProperty('statusCode')->isReadOnly());
        self::assertTrue($reflection->getProperty('headers')->isReadOnly());
    }

    #[Test]
    public function sendContentOutputsContent(): void
    {
        $response = new Response('Hello, World!');

        ob_start();
        $response->send();
        $output = ob_get_clean();

        self::assertSame('Hello, World!', $output);
    }

    #[Test]
    public function sendOutputsHeadersAndContent(): void
    {
        $response = new Response('<p>body</p>', 200, ['X-Foo' => 'bar']);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        self::assertSame('<p>body</p>', $output);
    }

    #[Test]
    public function sendHeadersSkipsWhenAlreadySent(): void
    {
        // In PHPUnit CLI, headers_sent() returns true after first output,
        // so sendHeaders() will early-return without error.
        $response = new Response('test', 404, ['X-Test' => 'value']);

        ob_start();
        $response->send();
        $output = ob_get_clean();

        self::assertSame('test', $output);
    }
}
