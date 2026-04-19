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

namespace WPPack\Component\Rest\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;

final class RestRouteTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $route = new RestRoute();

        self::assertSame('', $route->route);
        self::assertSame([], $route->methods);
        self::assertNull($route->namespace);
    }

    #[Test]
    public function classLevelWithNamespace(): void
    {
        $route = new RestRoute('/products', namespace: 'my-plugin/v1');

        self::assertSame('/products', $route->route);
        self::assertSame('my-plugin/v1', $route->namespace);
    }

    #[Test]
    public function methodLevelWithMethods(): void
    {
        $route = new RestRoute('/items', methods: [HttpMethod::GET, HttpMethod::POST]);

        self::assertSame('/items', $route->route);
        self::assertSame(['GET', 'POST'], $route->methods);
    }

    #[Test]
    public function singleMethodEnum(): void
    {
        $route = new RestRoute(methods: HttpMethod::POST);

        self::assertSame(['POST'], $route->methods);
    }

    #[Test]
    public function singleMethodString(): void
    {
        $route = new RestRoute(methods: 'POST');

        self::assertSame(['POST'], $route->methods);
    }

    #[Test]
    public function multipleMethodsEnum(): void
    {
        $route = new RestRoute(methods: [HttpMethod::PUT, HttpMethod::PATCH]);

        self::assertSame(['PUT', 'PATCH'], $route->methods);
    }

    #[Test]
    public function multipleMethodsString(): void
    {
        $route = new RestRoute(methods: ['PUT', 'PATCH']);

        self::assertSame(['PUT', 'PATCH'], $route->methods);
    }

    #[Test]
    public function mixedMethodTypes(): void
    {
        $route = new RestRoute(methods: [HttpMethod::GET, 'POST']);

        self::assertSame(['GET', 'POST'], $route->methods);
    }

    #[Test]
    public function methodsAreUppercased(): void
    {
        $route = new RestRoute(methods: 'get');

        self::assertSame(['GET'], $route->methods);
    }

    #[Test]
    public function isRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(RestRoute::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $flags = $attributes[0]->newInstance()->flags;
        self::assertNotSame(0, $flags & \Attribute::IS_REPEATABLE);
    }

    #[Test]
    public function targetsClassAndMethod(): void
    {
        $reflection = new \ReflectionClass(RestRoute::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertNotSame(0, $flags & \Attribute::TARGET_CLASS);
        self::assertNotSame(0, $flags & \Attribute::TARGET_METHOD);
    }

    #[Test]
    public function routeAsFirstPositionalArgument(): void
    {
        $route = new RestRoute('/users');

        self::assertSame('/users', $route->route);
    }

    #[Test]
    public function nameDefaultsToEmpty(): void
    {
        $route = new RestRoute();

        self::assertSame('', $route->name);
    }

    #[Test]
    public function nameIsStored(): void
    {
        $route = new RestRoute('/items', name: 'item_list');

        self::assertSame('item_list', $route->name);
    }

    #[Test]
    public function requirementsDefaultsToEmpty(): void
    {
        $route = new RestRoute();

        self::assertSame([], $route->requirements);
    }

    #[Test]
    public function requirementsAreStored(): void
    {
        $route = new RestRoute('/items/{id}', requirements: ['id' => '\d+']);

        self::assertSame(['id' => '\d+'], $route->requirements);
    }
}
