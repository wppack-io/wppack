<?php

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\FileBag;
use WpPack\Component\HttpFoundation\HeaderBag;
use WpPack\Component\HttpFoundation\ParameterBag;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\ServerBag;

final class RequestTest extends TestCase
{
    #[Test]
    public function constructorInitializesAllBags(): void
    {
        $request = new Request(
            query: ['q' => 'search'],
            post: ['name' => 'John'],
            cookies: ['session' => 'abc'],
            files: [],
            server: ['HTTP_HOST' => 'example.com'],
        );

        self::assertInstanceOf(ParameterBag::class, $request->query);
        self::assertInstanceOf(ParameterBag::class, $request->post);
        self::assertInstanceOf(ParameterBag::class, $request->cookies);
        self::assertInstanceOf(FileBag::class, $request->files);
        self::assertInstanceOf(ServerBag::class, $request->server);
        self::assertInstanceOf(HeaderBag::class, $request->headers);

        self::assertSame('search', $request->query->get('q'));
        self::assertSame('John', $request->post->get('name'));
        self::assertSame('abc', $request->cookies->get('session'));
    }

    #[Test]
    public function getChecksQueryFirst(): void
    {
        $request = new Request(
            query: ['key' => 'from_query'],
            post: ['key' => 'from_post'],
        );

        self::assertSame('from_query', $request->get('key'));
    }

    #[Test]
    public function getChecksPostWhenNotInQuery(): void
    {
        $request = new Request(
            query: [],
            post: ['key' => 'from_post'],
        );

        self::assertSame('from_post', $request->get('key'));
    }

    #[Test]
    public function getReturnsDefaultWhenKeyNotFound(): void
    {
        $request = new Request();

        self::assertNull($request->get('missing'));
        self::assertSame('default', $request->get('missing', 'default'));
    }

    #[Test]
    public function getMethodReturnsUppercaseMethod(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'post']);

        self::assertSame('POST', $request->getMethod());
    }

    #[Test]
    public function getMethodDefaultsToGet(): void
    {
        $request = new Request();

        self::assertSame('GET', $request->getMethod());
    }

    #[Test]
    public function isMethodComparesMethods(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'POST']);

        self::assertTrue($request->isMethod('POST'));
        self::assertTrue($request->isMethod('post'));
        self::assertFalse($request->isMethod('GET'));
    }

    #[Test]
    public function getUriReturnsRequestUri(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/foo/bar?q=1']);

        self::assertSame('/foo/bar?q=1', $request->getUri());
    }

    #[Test]
    public function getUriDefaultsToSlash(): void
    {
        $request = new Request();

        self::assertSame('/', $request->getUri());
    }

    #[Test]
    public function getPathInfoStripsQueryString(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/foo/bar?q=1&page=2']);

        self::assertSame('/foo/bar', $request->getPathInfo());
    }

    #[Test]
    public function getPathInfoReturnsUriWhenNoQueryString(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/foo/bar']);

        self::assertSame('/foo/bar', $request->getPathInfo());
    }

    #[Test]
    public function getSchemeReturnsHttpsWhenSecure(): void
    {
        $request = new Request(server: ['HTTPS' => 'on']);

        self::assertSame('https', $request->getScheme());
    }

    #[Test]
    public function getSchemeReturnsHttpWhenNotSecure(): void
    {
        $request = new Request();

        self::assertSame('http', $request->getScheme());
    }

    #[Test]
    public function getHostFromHostHeader(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);

        self::assertSame('example.com', $request->getHost());
    }

    #[Test]
    public function getHostFallsBackToServerName(): void
    {
        $request = new Request(server: ['SERVER_NAME' => 'fallback.com']);

        self::assertSame('fallback.com', $request->getHost());
    }

    #[Test]
    public function getPortFromServerPort(): void
    {
        $request = new Request(server: ['SERVER_PORT' => '8080']);

        self::assertSame(8080, $request->getPort());
    }

    #[Test]
    public function getPortDefaultsTo443WhenSecure(): void
    {
        $request = new Request(server: ['HTTPS' => 'on']);

        self::assertSame(443, $request->getPort());
    }

    #[Test]
    public function getPortDefaultsTo80WhenNotSecure(): void
    {
        $request = new Request();

        self::assertSame(80, $request->getPort());
    }

    #[Test]
    public function getClientIpFromXForwardedFor(): void
    {
        $request = new Request(server: [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 10.0.0.2',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        self::assertSame('10.0.0.1', $request->getClientIp());
    }

    #[Test]
    public function getClientIpFallsBackToRemoteAddr(): void
    {
        $request = new Request(server: ['REMOTE_ADDR' => '192.168.1.1']);

        self::assertSame('192.168.1.1', $request->getClientIp());
    }

    #[Test]
    public function getClientIpReturnsNullWhenNoIpAvailable(): void
    {
        $request = new Request();

        self::assertNull($request->getClientIp());
    }

    #[Test]
    public function getContentTypeFromHeader(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json']);

        self::assertSame('application/json', $request->getContentType());
    }

    #[Test]
    public function getContentTypeReturnsNullWhenMissing(): void
    {
        $request = new Request();

        self::assertNull($request->getContentType());
    }

    #[Test]
    public function getContentReturnsProvidedContent(): void
    {
        $request = new Request(content: '{"key":"value"}');

        self::assertSame('{"key":"value"}', $request->getContent());
    }

    #[Test]
    public function toArrayDecodesJsonContent(): void
    {
        $request = new Request(content: '{"name":"John","age":30}');

        $result = $request->toArray();

        self::assertSame(['name' => 'John', 'age' => 30], $result);
    }

    #[Test]
    public function toArrayReturnsEmptyArrayForEmptyContent(): void
    {
        $request = new Request(content: '');

        self::assertSame([], $request->toArray());
    }

    #[Test]
    public function isAjaxReturnsTrueWithXmlHttpRequestHeader(): void
    {
        $request = new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);

        self::assertTrue($request->isAjax());
    }

    #[Test]
    public function isAjaxReturnsFalseWithoutHeader(): void
    {
        $request = new Request();

        self::assertFalse($request->isAjax());
    }

    #[Test]
    public function isSecureReturnsTrueWithHttpsOn(): void
    {
        $request = new Request(server: ['HTTPS' => 'on']);

        self::assertTrue($request->isSecure());
    }

    #[Test]
    public function isSecureReturnsTrueWithPort443(): void
    {
        $request = new Request(server: ['SERVER_PORT' => '443']);

        self::assertTrue($request->isSecure());
    }

    #[Test]
    public function isSecureReturnsFalseForHttp(): void
    {
        $request = new Request();

        self::assertFalse($request->isSecure());
    }

    #[Test]
    public function isSecureReturnsFalseWhenHttpsOff(): void
    {
        $request = new Request(server: ['HTTPS' => 'off']);

        self::assertFalse($request->isSecure());
    }

    #[Test]
    public function isJsonReturnsTrueForJsonContentType(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json']);

        self::assertTrue($request->isJson());
    }

    #[Test]
    public function isJsonReturnsTrueForJsonContentTypeWithCharset(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'application/json; charset=utf-8']);

        self::assertTrue($request->isJson());
    }

    #[Test]
    public function isJsonReturnsFalseForNonJsonContentType(): void
    {
        $request = new Request(server: ['CONTENT_TYPE' => 'text/html']);

        self::assertFalse($request->isJson());
    }

    #[Test]
    public function isJsonReturnsFalseWhenNoContentType(): void
    {
        $request = new Request();

        self::assertFalse($request->isJson());
    }

    #[Test]
    public function getHostRemovesPortFromHostHeader(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com:8080']);

        self::assertSame('example.com', $request->getHost());
    }

    #[Test]
    public function toArrayThrowsJsonExceptionForInvalidJson(): void
    {
        $request = new Request(content: '{invalid json}');

        $this->expectException(\JsonException::class);

        $request->toArray();
    }

    #[Test]
    public function createFromGlobalsUsesSuperglobals(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            $_GET = ['q' => 'test'];
            $_POST = ['name' => 'Alice'];
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'test.local'];

            $request = Request::createFromGlobals();

            self::assertSame('test', $request->query->get('q'));
            self::assertSame('Alice', $request->post->get('name'));
            self::assertSame('POST', $request->getMethod());
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
    }
}
