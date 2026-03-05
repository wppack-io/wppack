<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\RoutePosition;

#[CoversClass(Route::class)]
#[CoversClass(RewriteTag::class)]
final class RouteAttributeTest extends TestCase
{
    #[Test]
    public function routeStoresAllProperties(): void
    {
        $route = new Route(
            name: 'product_detail',
            regex: '^products/([^/]+)/?$',
            query: 'index.php?product_slug=$matches[1]',
            position: RoutePosition::Bottom,
        );

        self::assertSame('product_detail', $route->name);
        self::assertSame('^products/([^/]+)/?$', $route->regex);
        self::assertSame('index.php?product_slug=$matches[1]', $route->query);
        self::assertSame(RoutePosition::Bottom, $route->position);
    }

    #[Test]
    public function routeDefaultsToTopPosition(): void
    {
        $route = new Route(
            name: 'default_route',
            regex: '^page/?$',
            query: 'index.php?pagename=page',
        );

        self::assertSame(RoutePosition::Top, $route->position);
    }

    #[Test]
    public function routeTargetsClassAndMethod(): void
    {
        $reflection = new \ReflectionClass(Route::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $expected = \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD;

        self::assertSame($expected, $attribute->flags);
    }

    #[Test]
    public function rewriteTagStoresProperties(): void
    {
        $tag = new RewriteTag(
            tag: '%product_slug%',
            regex: '([^/]+)',
        );

        self::assertSame('%product_slug%', $tag->tag);
        self::assertSame('([^/]+)', $tag->regex);
    }

    #[Test]
    public function rewriteTagIsRepeatable(): void
    {
        $reflection = new \ReflectionClass(RewriteTag::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $expected = \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE;

        self::assertSame($expected, $attribute->flags);
    }
}
