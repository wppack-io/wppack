<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Request;

final class RequestTest extends TestCase
{
    #[Test]
    public function getParamReturnsValue(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $wpRequest->set_param('id', 42);
        $request = new Request($wpRequest);

        self::assertSame(42, $request->getParam('id'));
    }

    #[Test]
    public function getParamsReturnsAll(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $wpRequest->set_param('foo', 'bar');
        $wpRequest->set_param('baz', 123);
        $request = new Request($wpRequest);

        $params = $request->getParams();
        self::assertSame('bar', $params['foo']);
        self::assertSame(123, $params['baz']);
    }

    #[Test]
    public function getHeaderReturnsValue(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $wpRequest->set_header('X-Custom', 'value');
        $request = new Request($wpRequest);

        self::assertSame('value', $request->getHeader('X-Custom'));
    }

    #[Test]
    public function getHeaderReturnsNullWhenMissing(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $request = new Request($wpRequest);

        self::assertNull($request->getHeader('X-Missing'));
    }

    #[Test]
    public function getMethodReturnsHttpMethod(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('POST', '/test');
        $request = new Request($wpRequest);

        self::assertSame('POST', $request->getMethod());
    }

    #[Test]
    public function getBodyReturnsRawBody(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('POST', '/test');
        $wpRequest->set_body('{"key":"value"}');
        $request = new Request($wpRequest);

        self::assertSame('{"key":"value"}', $request->getBody());
    }

    #[Test]
    public function getQueryParamsReturnsQueryParams(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $wpRequest->set_query_params(['search' => 'test']);
        $request = new Request($wpRequest);

        self::assertSame(['search' => 'test'], $request->getQueryParams());
    }

    #[Test]
    public function getWpRequestReturnsOriginal(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $request = new Request($wpRequest);

        self::assertSame($wpRequest, $request->getWpRequest());
    }

    #[Test]
    public function getHeadersReturnsAll(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $wpRequest->set_header('X-First', 'one');
        $wpRequest->set_header('X-Second', 'two');
        $request = new Request($wpRequest);

        $headers = $request->getHeaders();
        self::assertArrayHasKey('x_first', $headers);
        self::assertArrayHasKey('x_second', $headers);
    }

    #[Test]
    public function getJsonParamsReturnsDecodedBody(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('POST', '/test');
        $wpRequest->set_header('Content-Type', 'application/json');
        $wpRequest->set_body('{"title":"Hello","count":5}');
        $request = new Request($wpRequest);

        $params = $request->getJsonParams();
        self::assertSame('Hello', $params['title']);
        self::assertSame(5, $params['count']);
    }

    #[Test]
    public function getFileParamsReturnsFiles(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('POST', '/test');
        $wpRequest->set_file_params(['file' => ['name' => 'test.txt', 'size' => 100]]);
        $request = new Request($wpRequest);

        $files = $request->getFileParams();
        self::assertSame('test.txt', $files['file']['name']);
    }

    #[Test]
    public function getUrlParamsReturnsUrlParams(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('GET', '/test');
        $wpRequest->set_url_params(['id' => '42']);
        $request = new Request($wpRequest);

        self::assertSame(['id' => '42'], $request->getUrlParams());
    }

    #[Test]
    public function getBodyParamsReturnsBodyParams(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $wpRequest = new \WP_REST_Request('POST', '/test');
        $wpRequest->set_body_params(['name' => 'John', 'age' => '30']);
        $request = new Request($wpRequest);

        $params = $request->getBodyParams();
        self::assertSame('John', $params['name']);
        self::assertSame('30', $params['age']);
    }
}
