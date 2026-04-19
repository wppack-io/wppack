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

namespace WPPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Rest\Attribute\Permission;
use WPPack\Component\Rest\RestEntry;
use WPPack\Component\Rest\RestParamEntry;

final class RestEntryTest extends TestCase
{
    #[Test]
    public function storesAllProperties(): void
    {
        $permission = new Permission(public: true);
        $params = [new RestParamEntry('id', 'integer', true, null, null)];
        $handler = static fn() => null;

        $entry = new RestEntry(
            'my-plugin/v1',
            '/products',
            ['GET'],
            $permission,
            $params,
            $handler,
        );

        self::assertSame('my-plugin/v1', $entry->namespace);
        self::assertSame('/products', $entry->route);
        self::assertSame(['GET'], $entry->methods);
        self::assertSame($permission, $entry->permission);
        self::assertSame($params, $entry->params);
    }

    #[Test]
    public function registerCallsRegisterRoute(): void
    {
        $entry = new RestEntry(
            'test/v1',
            '/items',
            ['GET'],
            new Permission(public: true),
            [],
            static fn() => ['data' => []],
        );

        $entry->register();

        $routes = rest_get_server()->get_routes();
        self::assertArrayHasKey('/test/v1/items', $routes);
    }

    #[Test]
    public function callbackConvertsResponseToWpRestResponse(): void
    {

        $response = new \WPPack\Component\HttpFoundation\Response('', 200, ['X-Custom' => 'yes']);
        $entry = new RestEntry(
            'test/v1',
            '/response-items',
            ['GET'],
            new Permission(public: true),
            [],
            static fn(\WP_REST_Request $request) => $response,
        );

        $entry->register();

        $request = new \WP_REST_Request('GET', '/test/v1/response-items');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/response-items'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_REST_Response::class, $result);
        self::assertNull($result->get_data());
        self::assertSame(200, $result->get_status());
    }

    #[Test]
    public function callbackConvertsJsonResponseToWpRestResponse(): void
    {

        $response = new \WPPack\Component\HttpFoundation\JsonResponse(['id' => 1], 201);
        $entry = new RestEntry(
            'test/v1',
            '/created',
            ['POST'],
            new Permission(public: true),
            [],
            static fn(\WP_REST_Request $request) => $response,
        );

        $entry->register();

        $request = new \WP_REST_Request('POST', '/test/v1/created');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/created'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_REST_Response::class, $result);
        self::assertSame(['id' => 1], $result->get_data());
        self::assertSame(201, $result->get_status());
    }

    #[Test]
    public function callbackReturnsArrayAsRestResponse(): void
    {
        $entry = new RestEntry(
            'test/v1',
            '/array',
            ['GET'],
            new Permission(public: true),
            [],
            static fn(\WP_REST_Request $request) => ['items' => [1, 2, 3]],
        );

        $entry->register();

        $request = new \WP_REST_Request('GET', '/test/v1/array');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/array'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_REST_Response::class, $result);
        self::assertSame(['items' => [1, 2, 3]], $result->get_data());
    }

    #[Test]
    public function callbackReturnsNoContentForNull(): void
    {

        $entry = new RestEntry(
            'test/v1',
            '/delete',
            ['DELETE'],
            new Permission(public: true),
            [],
            static fn(\WP_REST_Request $request) => null,
        );

        $entry->register();

        $request = new \WP_REST_Request('DELETE', '/test/v1/delete');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/delete'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_REST_Response::class, $result);
        self::assertSame(204, $result->get_status());
    }

    #[Test]
    public function callbackConvertsHttpExceptionToWpError(): void
    {

        $entry = new RestEntry(
            'test/v1',
            '/error',
            ['GET'],
            new Permission(public: true),
            [],
            static function (\WP_REST_Request $request): never {
                throw new \WPPack\Component\HttpFoundation\Exception\NotFoundException('Item not found.');
            },
        );

        $entry->register();

        $request = new \WP_REST_Request('GET', '/test/v1/error');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/error'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_Error::class, $result);
        self::assertSame('http_not_found', $result->get_error_code());
        self::assertSame('Item not found.', $result->get_error_message());
        self::assertSame(['status' => 404], $result->get_error_data());
    }

    #[Test]
    public function callbackSetsResponseHeaders(): void
    {

        $response = new \WPPack\Component\HttpFoundation\Response('', 200, ['X-Rate-Limit' => '100']);
        $entry = new RestEntry(
            'test/v1',
            '/headers',
            ['GET'],
            new Permission(public: true),
            [],
            static fn(\WP_REST_Request $request) => $response,
        );

        $entry->register();

        $request = new \WP_REST_Request('GET', '/test/v1/headers');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/headers'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_REST_Response::class, $result);
        $headers = $result->get_headers();
        self::assertSame('100', $headers['X-Rate-Limit']);
    }

    #[Test]
    public function extractParamsFromPath(): void
    {
        self::assertSame(['id'], RestEntry::extractParams('/items/{id}'));
        self::assertSame(['year', 'month'], RestEntry::extractParams('/events/{year}/{month}'));
        self::assertSame([], RestEntry::extractParams('/static'));
    }
}
