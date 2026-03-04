<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Attribute\Route;
use WpPack\Component\Rest\HttpMethod;

final class RouteTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $route = new Route();

        self::assertSame('', $route->route);
        self::assertSame([], $route->methods);
        self::assertNull($route->namespace);
    }

    #[Test]
    public function classLevelWithNamespace(): void
    {
        $route = new Route('/products', namespace: 'my-plugin/v1');

        self::assertSame('/products', $route->route);
        self::assertSame('my-plugin/v1', $route->namespace);
    }

    #[Test]
    public function methodLevelWithMethods(): void
    {
        $route = new Route('/items', methods: [HttpMethod::GET, HttpMethod::POST]);

        self::assertSame('/items', $route->route);
        self::assertSame(['GET', 'POST'], $route->methods);
    }

    #[Test]
    public function singleMethodEnum(): void
    {
        $route = new Route(methods: HttpMethod::POST);

        self::assertSame(['POST'], $route->methods);
    }

    #[Test]
    public function singleMethodString(): void
    {
        $route = new Route(methods: 'POST');

        self::assertSame(['POST'], $route->methods);
    }

    #[Test]
    public function multipleMethodsEnum(): void
    {
        $route = new Route(methods: [HttpMethod::PUT, HttpMethod::PATCH]);

        self::assertSame(['PUT', 'PATCH'], $route->methods);
    }

    #[Test]
    public function multipleMethodsString(): void
    {
        $route = new Route(methods: ['PUT', 'PATCH']);

        self::assertSame(['PUT', 'PATCH'], $route->methods);
    }

    #[Test]
    public function mixedMethodTypes(): void
    {
        $route = new Route(methods: [HttpMethod::GET, 'POST']);

        self::assertSame(['GET', 'POST'], $route->methods);
    }

    #[Test]
    public function methodsAreUppercased(): void
    {
        $route = new Route(methods: 'get');

        self::assertSame(['GET'], $route->methods);
    }

    #[Test]
    public function isRepeatableAttribute(): void
    {
        $reflection = new \ReflectionClass(Route::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $flags = $attributes[0]->newInstance()->flags;
        self::assertNotSame(0, $flags & \Attribute::IS_REPEATABLE);
    }

    #[Test]
    public function targetsClassAndMethod(): void
    {
        $reflection = new \ReflectionClass(Route::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertNotSame(0, $flags & \Attribute::TARGET_CLASS);
        self::assertNotSame(0, $flags & \Attribute::TARGET_METHOD);
    }

    #[Test]
    public function routeAsFirstPositionalArgument(): void
    {
        $route = new Route('/users');

        self::assertSame('/users', $route->route);
    }
}
