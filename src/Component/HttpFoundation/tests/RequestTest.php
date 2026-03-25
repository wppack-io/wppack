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
    public function getChecksAttributesFirst(): void
    {
        $request = new Request(
            query: ['key' => 'from_query'],
            attributes: ['key' => 'from_attributes'],
            post: ['key' => 'from_post'],
        );

        self::assertSame('from_attributes', $request->get('key'));
    }

    #[Test]
    public function getChecksQueryWhenNotInAttributes(): void
    {
        $request = new Request(
            query: ['key' => 'from_query'],
            post: ['key' => 'from_post'],
        );

        self::assertSame('from_query', $request->get('key'));
    }

    #[Test]
    public function getChecksPostWhenNotInAttributesOrQuery(): void
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
    public function constructorInitializesAttributesBag(): void
    {
        $request = new Request(attributes: ['foo' => 'bar']);

        self::assertInstanceOf(ParameterBag::class, $request->attributes);
        self::assertSame('bar', $request->attributes->get('foo'));
    }

    #[Test]
    public function getPayloadParsesJsonBody(): void
    {
        $request = new Request(content: '{"name":"John","age":30}');

        $payload = $request->getPayload();

        self::assertInstanceOf(ParameterBag::class, $payload);
        self::assertSame('John', $payload->getString('name'));
        self::assertSame(30, $payload->getInt('age'));
    }

    #[Test]
    public function getPayloadReturnsPostDataWhenPostIsNotEmpty(): void
    {
        $request = new Request(
            post: ['title' => 'Hello'],
            content: '{"title":"FromBody"}',
        );

        $payload = $request->getPayload();

        self::assertSame('Hello', $payload->getString('title'));
    }

    #[Test]
    public function getPayloadCachesResult(): void
    {
        $request = new Request(content: '{"key":"value"}');

        $payload1 = $request->getPayload();
        $payload2 = $request->getPayload();

        self::assertSame($payload1, $payload2);
    }

    #[Test]
    public function getPayloadReturnsEmptyBagForEmptyContent(): void
    {
        $request = new Request(content: '');

        $payload = $request->getPayload();

        self::assertSame(0, $payload->count());
    }

    #[Test]
    public function createFromGlobalsInitializesEmptyAttributes(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'GET'];

            $request = Request::createFromGlobals();

            self::assertSame(0, $request->attributes->count());
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
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

    #[Test]
    public function createFromGlobalsUnslashesQueryParams(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            // Simulate wp_magic_quotes(): addslashes applied to superglobals
            $_GET = add_magic_quotes(['name' => "O'Brien", 'search' => 'it\'s a "test"']);
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'GET'];

            $request = Request::createFromGlobals();

            self::assertSame("O'Brien", $request->query->get('name'));
            self::assertSame('it\'s a "test"', $request->query->get('search'));
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
    }

    #[Test]
    public function createFromGlobalsUnslashesPostParams(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            $_GET = [];
            // Simulate wp_magic_quotes()
            $_POST = add_magic_quotes(['title' => 'Hello "World"', 'body' => "It's great"]);
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'POST'];

            $request = Request::createFromGlobals();

            self::assertSame('Hello "World"', $request->post->get('title'));
            self::assertSame("It's great", $request->post->get('body'));
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
    }

    #[Test]
    public function createFromGlobalsUnslashesCookies(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            $_GET = [];
            $_POST = [];
            // Simulate wp_magic_quotes()
            $_COOKIE = add_magic_quotes(['token' => "abc'def"]);
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'GET'];

            $request = Request::createFromGlobals();

            self::assertSame("abc'def", $request->cookies->get('token'));
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
    }

    #[Test]
    public function createFromGlobalsUnslashesServerVars(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            $_GET = [];
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];
            // Simulate wp_magic_quotes() on $_SERVER
            $_SERVER = add_magic_quotes([
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => "/search?q=it's",
                'HTTP_HOST' => 'example.com',
            ]);

            $request = Request::createFromGlobals();

            self::assertSame("/search?q=it's", $request->getUri());
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
    }

    #[Test]
    public function createFromGlobalsMatchesWpRestRequestValues(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        try {
            // Simulate wp_magic_quotes() applied to superglobals
            $_GET = add_magic_quotes(['name' => "O'Brien", 'tag' => 'rock&roll']);
            $_POST = add_magic_quotes(['content' => 'She said "hello"']);
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/x-www-form-urlencoded'];

            // WP_REST_Request uses wp_unslash() internally (same as WP_REST_Server::serve_request)
            $wpRequest = new \WP_REST_Request('POST', '/test');
            $wpRequest->set_query_params(wp_unslash($_GET));
            $wpRequest->set_body_params(wp_unslash($_POST));

            // HttpFoundation Request should produce identical values
            $request = Request::createFromGlobals();

            self::assertSame($wpRequest->get_param('name'), $request->query->get('name'));
            self::assertSame($wpRequest->get_param('tag'), $request->query->get('tag'));
            self::assertSame($wpRequest->get_param('content'), $request->post->get('content'));
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
        }
    }

    #[Test]
    public function createFromGlobalsSkipsUnslashBeforeMagicQuotes(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        // Simulate pre-wp_magic_quotes state
        Request::disableMagicQuotesHandling();

        try {
            // Raw superglobals without addslashes (before wp_magic_quotes)
            $_GET = ['path' => 'C:\\Users\\test'];
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'GET'];

            $request = Request::createFromGlobals();

            // Backslash should be preserved since wp_unslash is NOT applied
            self::assertSame('C:\\Users\\test', $request->query->get('path'));
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
            Request::resetMagicQuotesHandling();
        }
    }

    #[Test]
    public function enableMagicQuotesHandlingForcesUnslash(): void
    {
        $origGet = $_GET;
        $origPost = $_POST;
        $origCookie = $_COOKIE;
        $origFiles = $_FILES;
        $origServer = $_SERVER;

        Request::disableMagicQuotesHandling();

        try {
            // Manually slashed data
            $_GET = add_magic_quotes(['name' => "O'Brien"]);
            $_POST = [];
            $_COOKIE = [];
            $_FILES = [];
            $_SERVER = ['REQUEST_METHOD' => 'GET'];

            // Without explicit enable, backslash remains
            $before = Request::createFromGlobals();
            self::assertSame("O\\'Brien", $before->query->get('name'));

            // After explicit enable, unslash is applied
            Request::enableMagicQuotesHandling();
            $after = Request::createFromGlobals();
            self::assertSame("O'Brien", $after->query->get('name'));
        } finally {
            $_GET = $origGet;
            $_POST = $origPost;
            $_COOKIE = $origCookie;
            $_FILES = $origFiles;
            $_SERVER = $origServer;
            Request::resetMagicQuotesHandling();
        }
    }
}
