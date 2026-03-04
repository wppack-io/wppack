<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteEntry;
use WpPack\Component\Routing\RoutePosition;

final class RouteEntryTest extends TestCase
{
    #[Test]
    public function parsesQueryVarsFromQueryString(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        self::assertSame(['test_slug'], $entry->queryVars);
    }

    #[Test]
    public function parsesMultipleQueryVarsFromQueryString(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^events/(\d{4})/(\d{2})/?$',
            'index.php?event_year=$matches[1]&event_month=$matches[2]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        self::assertSame(['event_year', 'event_month'], $entry->queryVars);
    }

    #[Test]
    public function excludesStaticQueryVarsFromParsing(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^events/(\d{4})/?$',
            'index.php?post_type=event&event_year=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        self::assertSame(['event_year'], $entry->queryVars);
    }

    #[Test]
    public function parsesEmptyQueryVars(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^static-page/?$',
            'index.php?pagename=static',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        self::assertSame([], $entry->queryVars);
    }

    #[Test]
    public function filterQueryVarsAddsCustomVars(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        $vars = $entry->filterQueryVars(['existing_var']);

        self::assertContains('existing_var', $vars);
        self::assertContains('test_slug', $vars);
    }

    #[Test]
    public function registerRouteCallsAddRewriteRule(): void
    {
        if (!function_exists('add_rewrite_rule')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [['%test_slug%', '([^/]+)']],
            fn() => null,
        );

        $entry->registerRoute();

        global $wp_rewrite;
        self::assertArrayHasKey('^test/([^/]+)/?$', $wp_rewrite->extra_rules_top);
    }

    #[Test]
    public function handleTemplateRedirectCallsHandlerWhenMatches(): void
    {
        if (!function_exists('get_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $called = false;
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function () use (&$called) {
                $called = true;

                return new TemplateResponse('/path/to/template.php');
            },
        );

        set_query_var('test_slug', 'hello');
        $entry->handleTemplateRedirect();

        self::assertTrue($called);
    }

    #[Test]
    public function handleTemplateRedirectSkipsWhenNoMatch(): void
    {
        if (!function_exists('get_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $called = false;
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function () use (&$called) {
                $called = true;

                return null;
            },
        );

        set_query_var('test_slug', '');
        $entry->handleTemplateRedirect();

        self::assertFalse($called);
    }
}
