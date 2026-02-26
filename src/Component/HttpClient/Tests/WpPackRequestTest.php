<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\Stream;
use WpPack\Component\HttpClient\WpPackRequest;
use WpPack\Component\HttpClient\WpPackUri;

final class WpPackRequestTest extends TestCase
{
    #[Test]
    public function constructWithStringUri(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com/path');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://example.com/path', (string) $request->getUri());
    }

    #[Test]
    public function constructWithUriObject(): void
    {
        $uri = new WpPackUri('https://example.com');
        $request = new WpPackRequest('POST', $uri);

        self::assertSame('POST', $request->getMethod());
        self::assertSame($uri, $request->getUri());
    }

    #[Test]
    public function constructWithHeaders(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', [
            'Content-Type' => 'application/json',
            'Accept' => ['text/html', 'application/json'],
        ]);

        self::assertSame(['application/json'], $request->getHeader('Content-Type'));
        self::assertSame(['text/html', 'application/json'], $request->getHeader('Accept'));
    }

    #[Test]
    public function constructWithStringBody(): void
    {
        $request = new WpPackRequest('POST', 'https://example.com', body: '{"key":"value"}');

        self::assertSame('{"key":"value"}', (string) $request->getBody());
    }

    #[Test]
    public function constructWithStreamBody(): void
    {
        $stream = new Stream('stream body');
        $request = new WpPackRequest('POST', 'https://example.com', body: $stream);

        self::assertSame('stream body', (string) $request->getBody());
    }

    #[Test]
    public function methodIsUppercased(): void
    {
        $request = new WpPackRequest('get', 'https://example.com');

        self::assertSame('GET', $request->getMethod());
    }

    #[Test]
    public function hostHeaderAutoSet(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com:8080');

        self::assertTrue($request->hasHeader('Host'));
        self::assertSame('example.com:8080', $request->getHeaderLine('Host'));
    }

    #[Test]
    public function hostHeaderNotAutoSetForEmptyHost(): void
    {
        $request = new WpPackRequest('GET', '/relative');

        self::assertFalse($request->hasHeader('Host'));
    }

    #[Test]
    public function withMethod(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $new = $request->withMethod('POST');

        self::assertSame('GET', $request->getMethod());
        self::assertSame('POST', $new->getMethod());
    }

    #[Test]
    public function withUri(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $newUri = new WpPackUri('https://other.com');
        $new = $request->withUri($newUri);

        self::assertSame('https://example.com', (string) $request->getUri());
        self::assertSame('https://other.com', (string) $new->getUri());
        self::assertSame('other.com', $new->getHeaderLine('Host'));
    }

    #[Test]
    public function withUriPreserveHost(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $newUri = new WpPackUri('https://other.com');
        $new = $request->withUri($newUri, preserveHost: true);

        self::assertSame('example.com', $new->getHeaderLine('Host'));
    }

    #[Test]
    public function getRequestTarget(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com/path?query=1');

        self::assertSame('/path?query=1', $request->getRequestTarget());
    }

    #[Test]
    public function getRequestTargetDefaultsToSlash(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');

        self::assertSame('/', $request->getRequestTarget());
    }

    #[Test]
    public function withRequestTarget(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $new = $request->withRequestTarget('/custom');

        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('/custom', $new->getRequestTarget());
    }

    #[Test]
    public function getProtocolVersion(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', protocolVersion: '2.0');

        self::assertSame('2.0', $request->getProtocolVersion());
    }

    #[Test]
    public function withProtocolVersion(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $new = $request->withProtocolVersion('2.0');

        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('2.0', $new->getProtocolVersion());
    }

    #[Test]
    public function withHeader(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $new = $request->withHeader('X-Custom', 'value');

        self::assertFalse($request->hasHeader('X-Custom'));
        self::assertSame(['value'], $new->getHeader('X-Custom'));
    }

    #[Test]
    public function withHeaderReplacesExisting(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', ['X-Custom' => 'old']);
        $new = $request->withHeader('X-Custom', 'new');

        self::assertSame(['new'], $new->getHeader('X-Custom'));
    }

    #[Test]
    public function headersCaseInsensitive(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', ['Content-Type' => 'text/html']);

        self::assertTrue($request->hasHeader('content-type'));
        self::assertSame(['text/html'], $request->getHeader('CONTENT-TYPE'));
    }

    #[Test]
    public function withAddedHeader(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', ['Accept' => 'text/html']);
        $new = $request->withAddedHeader('Accept', 'application/json');

        self::assertSame(['text/html', 'application/json'], $new->getHeader('Accept'));
    }

    #[Test]
    public function withAddedHeaderNewHeader(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');
        $new = $request->withAddedHeader('X-New', 'value');

        self::assertSame(['value'], $new->getHeader('X-New'));
    }

    #[Test]
    public function withoutHeader(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', ['X-Remove' => 'value']);
        $new = $request->withoutHeader('X-Remove');

        self::assertTrue($request->hasHeader('X-Remove'));
        self::assertFalse($new->hasHeader('X-Remove'));
    }

    #[Test]
    public function withBody(): void
    {
        $request = new WpPackRequest('POST', 'https://example.com', body: 'old');
        $new = $request->withBody(new Stream('new'));

        self::assertSame('old', (string) $request->getBody());
        self::assertSame('new', (string) $new->getBody());
    }

    #[Test]
    public function getHeaderLine(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', [
            'Accept' => ['text/html', 'application/json'],
        ]);

        self::assertSame('text/html, application/json', $request->getHeaderLine('Accept'));
    }

    #[Test]
    public function getHeaderLineNonExistent(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');

        self::assertSame('', $request->getHeaderLine('X-Missing'));
    }

    #[Test]
    public function getNonExistentHeader(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com');

        self::assertSame([], $request->getHeader('X-Missing'));
    }

    #[Test]
    public function getHeaders(): void
    {
        $request = new WpPackRequest('GET', 'https://example.com', [
            'Content-Type' => 'application/json',
            'Accept' => 'text/html',
        ]);

        $headers = $request->getHeaders();
        self::assertArrayHasKey('Content-Type', $headers);
        self::assertArrayHasKey('Accept', $headers);
        self::assertArrayHasKey('Host', $headers);
    }
}
