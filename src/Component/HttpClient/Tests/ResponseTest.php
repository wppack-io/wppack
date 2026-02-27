<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\Exception\RequestException;
use WpPack\Component\HttpClient\Stream;
use WpPack\Component\HttpClient\Response;

final class ResponseTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $response = new Response();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', $response->getReasonPhrase());
        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame('', (string) $response->getBody());
    }

    #[Test]
    public function constructWithAll(): void
    {
        $response = new Response(
            statusCode: 404,
            headers: ['Content-Type' => 'text/html'],
            body: 'Not Found',
            protocolVersion: '2.0',
            reasonPhrase: 'Not Found',
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('2.0', $response->getProtocolVersion());
        self::assertSame('Not Found', (string) $response->getBody());
        self::assertSame(['text/html'], $response->getHeader('Content-Type'));
    }

    #[Test]
    public function constructWithStreamBody(): void
    {
        $stream = new Stream('stream content');
        $response = new Response(body: $stream);

        self::assertSame('stream content', (string) $response->getBody());
    }

    #[Test]
    public function withStatus(): void
    {
        $response = new Response(200);
        $new = $response->withStatus(301, 'Moved Permanently');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(301, $new->getStatusCode());
        self::assertSame('Moved Permanently', $new->getReasonPhrase());
    }

    #[Test]
    public function withProtocolVersion(): void
    {
        $response = new Response();
        $new = $response->withProtocolVersion('2.0');

        self::assertSame('1.1', $response->getProtocolVersion());
        self::assertSame('2.0', $new->getProtocolVersion());
    }

    #[Test]
    public function withHeader(): void
    {
        $response = new Response();
        $new = $response->withHeader('X-Custom', 'value');

        self::assertFalse($response->hasHeader('X-Custom'));
        self::assertSame(['value'], $new->getHeader('X-Custom'));
    }

    #[Test]
    public function withAddedHeader(): void
    {
        $response = new Response(headers: ['Accept' => 'text/html']);
        $new = $response->withAddedHeader('Accept', 'application/json');

        self::assertSame(['text/html', 'application/json'], $new->getHeader('Accept'));
    }

    #[Test]
    public function withoutHeader(): void
    {
        $response = new Response(headers: ['X-Remove' => 'value']);
        $new = $response->withoutHeader('X-Remove');

        self::assertTrue($response->hasHeader('X-Remove'));
        self::assertFalse($new->hasHeader('X-Remove'));
    }

    #[Test]
    public function withBody(): void
    {
        $response = new Response(body: 'old');
        $new = $response->withBody(new Stream('new'));

        self::assertSame('old', (string) $response->getBody());
        self::assertSame('new', (string) $new->getBody());
    }

    #[Test]
    public function headersCaseInsensitive(): void
    {
        $response = new Response(headers: ['Content-Type' => 'text/html']);

        self::assertTrue($response->hasHeader('content-type'));
        self::assertSame(['text/html'], $response->getHeader('CONTENT-TYPE'));
        self::assertSame('text/html', $response->getHeaderLine('content-type'));
    }

    #[Test]
    public function getHeaderLineNonExistent(): void
    {
        $response = new Response();

        self::assertSame('', $response->getHeaderLine('X-Missing'));
    }

    // --- Fluent helpers ---

    #[Test]
    public function statusHelper(): void
    {
        $response = new Response(201);

        self::assertSame(201, $response->status());
    }

    #[Test]
    public function headers(): void
    {
        $response = new Response(headers: ['X-Foo' => 'bar']);

        $headers = $response->headers();
        self::assertArrayHasKey('X-Foo', $headers);
        self::assertSame(['bar'], $headers['X-Foo']);
    }

    #[Test]
    public function header(): void
    {
        $response = new Response(headers: ['Content-Type' => 'application/json']);

        self::assertSame('application/json', $response->header('Content-Type'));
        self::assertNull($response->header('X-Missing'));
    }

    #[Test]
    public function body(): void
    {
        $response = new Response(body: 'hello world');

        self::assertSame('hello world', $response->body());
    }

    #[Test]
    public function json(): void
    {
        $response = new Response(body: '{"key":"value","num":42}');

        self::assertSame(['key' => 'value', 'num' => 42], $response->json());
    }

    #[Test]
    public function jsonInvalidReturnsEmptyArray(): void
    {
        $response = new Response(body: 'not json');

        self::assertSame([], $response->json());
    }

    #[Test]
    public function successful(): void
    {
        self::assertTrue((new Response(200))->successful());
        self::assertTrue((new Response(201))->successful());
        self::assertTrue((new Response(299))->successful());
        self::assertFalse((new Response(300))->successful());
        self::assertFalse((new Response(400))->successful());
        self::assertFalse((new Response(500))->successful());
    }

    #[Test]
    public function failed(): void
    {
        self::assertFalse((new Response(200))->failed());
        self::assertFalse((new Response(301))->failed());
        self::assertTrue((new Response(400))->failed());
        self::assertTrue((new Response(404))->failed());
        self::assertTrue((new Response(500))->failed());
        self::assertTrue((new Response(599))->failed());
    }

    #[Test]
    public function clientError(): void
    {
        self::assertFalse((new Response(200))->clientError());
        self::assertTrue((new Response(400))->clientError());
        self::assertTrue((new Response(404))->clientError());
        self::assertTrue((new Response(499))->clientError());
        self::assertFalse((new Response(500))->clientError());
    }

    #[Test]
    public function serverError(): void
    {
        self::assertFalse((new Response(200))->serverError());
        self::assertFalse((new Response(400))->serverError());
        self::assertTrue((new Response(500))->serverError());
        self::assertTrue((new Response(503))->serverError());
        self::assertTrue((new Response(599))->serverError());
    }

    #[Test]
    public function throwOnSuccess(): void
    {
        $response = new Response(200);

        self::assertSame($response, $response->throw());
    }

    #[Test]
    public function throwOnClientError(): void
    {
        $response = new Response(404);

        $this->expectException(RequestException::class);
        $response->throw();
    }

    #[Test]
    public function throwOnServerError(): void
    {
        $response = new Response(500);

        $this->expectException(RequestException::class);
        $response->throw();
    }

    #[Test]
    public function throwExceptionContainsResponse(): void
    {
        $response = new Response(422, body: '{"error":"validation"}');

        try {
            $response->throw();
            self::fail('Expected RequestException');
        } catch (RequestException $e) {
            self::assertSame($response, $e->response);
            self::assertSame(422, $e->response->getStatusCode());
        }
    }
}
