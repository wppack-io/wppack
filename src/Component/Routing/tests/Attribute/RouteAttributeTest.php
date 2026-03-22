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
    public function routeStoresPathAsFirstArgument(): void
    {
        $route = new Route('/products/{product_slug}', name: 'product_detail');

        self::assertSame('/products/{product_slug}', $route->path);
        self::assertSame('product_detail', $route->name);
    }

    #[Test]
    public function routeStoresAllProperties(): void
    {
        $route = new Route(
            '/events/{year}/{month}',
            name: 'event_archive',
            requirements: ['year' => '\d{4}', 'month' => '\d{2}'],
            vars: ['post_type' => 'event'],
            position: RoutePosition::Bottom,
        );

        self::assertSame('/events/{year}/{month}', $route->path);
        self::assertSame('event_archive', $route->name);
        self::assertSame(['year' => '\d{4}', 'month' => '\d{2}'], $route->requirements);
        self::assertSame(['post_type' => 'event'], $route->vars);
        self::assertSame(RoutePosition::Bottom, $route->position);
    }

    #[Test]
    public function routeDefaultsToTopPosition(): void
    {
        $route = new Route('/page', name: 'default_route');

        self::assertSame(RoutePosition::Top, $route->position);
    }

    #[Test]
    public function routeDefaultsToEmptyRequirementsAndVars(): void
    {
        $route = new Route('/simple', name: 'simple');

        self::assertSame([], $route->requirements);
        self::assertSame([], $route->vars);
    }

    #[Test]
    public function routeDefaultsToEmptyName(): void
    {
        $route = new Route('/no-name');

        self::assertSame('', $route->name);
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
