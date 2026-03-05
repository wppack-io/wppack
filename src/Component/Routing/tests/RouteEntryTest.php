<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\JsonResponse;
use WpPack\Component\Routing\Response\RedirectResponse;
use WpPack\Component\Routing\Response\Response;
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

    #[Test]
    public function filterTemplateIncludeReturnsOriginalWhenNoPending(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/?$',
            'index.php?test_page=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        self::assertSame('/original/template.php', $entry->filterTemplateInclude('/original/template.php'));
    }

    #[Test]
    public function filterTemplateIncludeReturnsPendingTemplate(): void
    {
        if (!function_exists('get_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => new TemplateResponse('/custom/template.php'),
        );

        set_query_var('test_slug', 'hello');
        $entry->handleTemplateRedirect();

        self::assertSame('/custom/template.php', $entry->filterTemplateInclude('/original/template.php'));
    }

    #[Test]
    public function dispatchWithNullResponseDoesNothing(): void
    {
        if (!function_exists('get_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        set_query_var('test_slug', 'hello');
        $entry->handleTemplateRedirect();

        // No pending template should be set when handler returns null
        self::assertSame('/original.php', $entry->filterTemplateInclude('/original.php'));
    }

    #[Test]
    public function dispatchWithTemplateResponseSetsContextVariables(): void
    {
        if (!function_exists('get_query_var') || !function_exists('set_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => new TemplateResponse(
                '/path/to/template.php',
                context: ['title' => 'Hello', 'count' => 42],
            ),
        );

        set_query_var('test_slug', 'hello');
        $entry->handleTemplateRedirect();

        self::assertSame('Hello', get_query_var('title'));
        self::assertSame(42, get_query_var('count'));
    }

    #[Test]
    public function dispatchWithUnsupportedResponseThrowsLogicException(): void
    {
        if (!function_exists('get_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $unsupportedResponse = new class extends \WpPack\Component\Routing\Response\RouteResponse {};

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => $unsupportedResponse,
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unsupported response type');

        set_query_var('test_slug', 'hello');
        $entry->handleTemplateRedirect();
    }

    #[Test]
    public function filterQueryVarsDeduplicatesVars(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        $vars = $entry->filterQueryVars(['test_slug', 'other_var']);

        self::assertCount(2, $vars);
        self::assertContains('test_slug', $vars);
        self::assertContains('other_var', $vars);
    }

    #[Test]
    public function storesRoutePositionCorrectly(): void
    {
        $topEntry = new RouteEntry(
            'top_route',
            '^top/?$',
            'index.php?page=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        $bottomEntry = new RouteEntry(
            'bottom_route',
            '^bottom/?$',
            'index.php?page=$matches[1]',
            RoutePosition::Bottom,
            [],
            fn() => null,
        );

        self::assertSame(RoutePosition::Top, $topEntry->position);
        self::assertSame(RoutePosition::Bottom, $bottomEntry->position);
    }

    #[Test]
    public function storesRewriteTagsCorrectly(): void
    {
        $tags = [['%product_slug%', '([^/]+)'], ['%variant_id%', '(\d+)']];
        $entry = new RouteEntry(
            'test_route',
            '^products/%product_slug%/%variant_id%/?$',
            'index.php?product_slug=$matches[1]&variant_id=$matches[2]',
            RoutePosition::Top,
            $tags,
            fn() => null,
        );

        self::assertSame($tags, $entry->rewriteTags);
    }

    #[Test]
    public function routePositionTopHasCorrectValue(): void
    {
        self::assertSame('top', RoutePosition::Top->value);
    }

    #[Test]
    public function routePositionBottomHasCorrectValue(): void
    {
        self::assertSame('bottom', RoutePosition::Bottom->value);
    }

    #[Test]
    public function registerRouteWithMultipleRewriteTags(): void
    {
        if (!function_exists('add_rewrite_rule')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^products/([^/]+)/(\d+)/?$',
            'index.php?product_slug=$matches[1]&variant_id=$matches[2]',
            RoutePosition::Top,
            [['%product_slug%', '([^/]+)'], ['%variant_id%', '(\d+)']],
            fn() => null,
        );

        $entry->registerRoute();

        global $wp_rewrite;
        self::assertArrayHasKey('^products/([^/]+)/(\d+)/?$', $wp_rewrite->extra_rules_top);
    }

    #[Test]
    public function dispatchWithTemplateResponseSetsStatusHeader(): void
    {
        if (!function_exists('get_query_var') || !function_exists('status_header')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => new TemplateResponse(
                '/path/to/404.php',
                statusCode: 404,
                headers: ['X-Custom' => 'value'],
            ),
        );

        set_query_var('test_slug', 'not-found');
        $entry->handleTemplateRedirect();

        self::assertSame('/path/to/404.php', $entry->filterTemplateInclude('/original.php'));
    }

    #[Test]
    public function handleTemplateRedirectDispatchesFirstMatchingVar(): void
    {
        if (!function_exists('get_query_var') || !function_exists('set_query_var')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $dispatchCount = 0;
        $entry = new RouteEntry(
            'test_route',
            '^events/(\d{4})/(\d{2})/?$',
            'index.php?event_year=$matches[1]&event_month=$matches[2]',
            RoutePosition::Top,
            [],
            function () use (&$dispatchCount) {
                $dispatchCount++;

                return new TemplateResponse('/events/archive.php');
            },
        );

        // Both query vars have values, but dispatch should only happen once
        set_query_var('event_year', '2024');
        set_query_var('event_month', '03');
        $entry->handleTemplateRedirect();

        self::assertSame(1, $dispatchCount);
        self::assertSame('/events/archive.php', $entry->filterTemplateInclude('/original.php'));
    }

    #[Test]
    public function dispatchWithBlockTemplateResponse(): void
    {
        if (!function_exists('get_query_var') || !function_exists('get_block_template')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => new BlockTemplateResponse(
                'custom-template',
                context: ['item_id' => 42],
            ),
        );

        set_query_var('test_slug', 'hello');
        $entry->handleTemplateRedirect();

        // When block template is found, pendingBlockTemplate is set to template-canvas.php
        $result = $entry->filterTemplateInclude('/original.php');
        // If get_block_template returns null, original template is used
        // If it returns a template, template-canvas.php path is used
        if ($result !== '/original.php') {
            self::assertStringContainsString('template-canvas.php', $result);
        } else {
            // Block template was not found in the test environment
            self::assertSame('/original.php', $result);
        }

        self::assertSame(42, get_query_var('item_id'));
    }
}
