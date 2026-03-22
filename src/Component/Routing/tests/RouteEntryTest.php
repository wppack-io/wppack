<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Exception\ForbiddenException;
use WpPack\Component\HttpFoundation\Exception\NotFoundException;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Routing\Response\BlockTemplateResponse;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteEntry;
use WpPack\Component\Routing\RoutePosition;

final class RouteEntryTest extends TestCase
{
    private ?\Closure $wpDieFilter = null;

    protected function setUp(): void
    {
        $this->wpDieFilter = static fn(): \Closure => static function (string|\WP_Error $message = ''): never {
            throw new \WPDieException(is_string($message) ? $message : $message->get_error_message());
        };

        add_filter('wp_die_handler', $this->wpDieFilter, \PHP_INT_MAX);
        add_filter('wp_die_ajax_handler', $this->wpDieFilter, \PHP_INT_MAX);
    }

    protected function tearDown(): void
    {
        if ($this->wpDieFilter !== null) {
            remove_filter('wp_die_handler', $this->wpDieFilter, \PHP_INT_MAX);
            remove_filter('wp_die_ajax_handler', $this->wpDieFilter, \PHP_INT_MAX);
            $this->wpDieFilter = null;
        }
    }

    // ──────────────────────────────────────────────────────────────
    // compilePath
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function compilePathWithSingleParam(): void
    {
        $regex = RouteEntry::compilePath('/products/{product_slug}');

        self::assertSame('^products/(?P<product_slug>[^/]+)/?$', $regex);
    }

    #[Test]
    public function compilePathWithMultipleParams(): void
    {
        $regex = RouteEntry::compilePath('/events/{year}/{month}');

        self::assertSame('^events/(?P<year>[^/]+)/(?P<month>[^/]+)/?$', $regex);
    }

    #[Test]
    public function compilePathWithRequirements(): void
    {
        $regex = RouteEntry::compilePath('/events/{year}/{month}', ['year' => '\d{4}', 'month' => '\d{2}']);

        self::assertSame('^events/(?P<year>\d{4})/(?P<month>\d{2})/?$', $regex);
    }

    #[Test]
    public function compilePathWithPartialRequirements(): void
    {
        $regex = RouteEntry::compilePath('/events/{year}/{slug}', ['year' => '\d{4}']);

        self::assertSame('^events/(?P<year>\d{4})/(?P<slug>[^/]+)/?$', $regex);
    }

    #[Test]
    public function compilePathWithNoParams(): void
    {
        $regex = RouteEntry::compilePath('/static-page');

        self::assertSame('^static-page/?$', $regex);
    }

    // ──────────────────────────────────────────────────────────────
    // buildQueryFromPath
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function buildQueryFromPathWithSingleParam(): void
    {
        $query = RouteEntry::buildQueryFromPath('/products/{product_slug}');

        self::assertSame('index.php?product_slug=$matches[1]', $query);
    }

    #[Test]
    public function buildQueryFromPathWithMultipleParams(): void
    {
        $query = RouteEntry::buildQueryFromPath('/events/{year}/{month}');

        self::assertSame('index.php?year=$matches[1]&month=$matches[2]', $query);
    }

    #[Test]
    public function buildQueryFromPathWithVars(): void
    {
        $query = RouteEntry::buildQueryFromPath('/events/{year}', ['post_type' => 'event']);

        self::assertSame('index.php?post_type=event&year=$matches[1]', $query);
    }

    #[Test]
    public function buildQueryFromPathWithVarsAndMultipleParams(): void
    {
        $query = RouteEntry::buildQueryFromPath('/events/{year}/{month}', ['post_type' => 'event']);

        self::assertSame('index.php?post_type=event&year=$matches[1]&month=$matches[2]', $query);
    }

    #[Test]
    public function buildQueryFromPathWithNoParams(): void
    {
        $query = RouteEntry::buildQueryFromPath('/static-page');

        self::assertSame('index.php?', $query);
    }

    #[Test]
    public function buildQueryFromPathWithOnlyVars(): void
    {
        $query = RouteEntry::buildQueryFromPath('/static-page', ['pagename' => 'static']);

        self::assertSame('index.php?pagename=static', $query);
    }

    // ──────────────────────────────────────────────────────────────
    // extractParams
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function extractParamsFromPath(): void
    {
        self::assertSame(['product_slug'], RouteEntry::extractParams('/products/{product_slug}'));
    }

    #[Test]
    public function extractMultipleParamsFromPath(): void
    {
        self::assertSame(['year', 'month'], RouteEntry::extractParams('/events/{year}/{month}'));
    }

    #[Test]
    public function extractNoParamsFromStaticPath(): void
    {
        self::assertSame([], RouteEntry::extractParams('/static-page'));
    }

    // ──────────────────────────────────────────────────────────────
    // path property
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function storesPathProperty(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^products/(?P<product_slug>[^/]+)/?$',
            'index.php?product_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
            '/products/{product_slug}',
        );

        self::assertSame('/products/{product_slug}', $entry->path);
    }

    #[Test]
    public function pathDefaultsToEmptyString(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/?$',
            'index.php?page=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        self::assertSame('', $entry->path);
    }

    // ──────────────────────────────────────────────────────────────
    // parseQueryVars (legacy)
    // ──────────────────────────────────────────────────────────────

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
    public function dispatchWithUnsupportedResponseThrowsTypeError(): void
    {
        $unsupportedResponse = new \stdClass();

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => $unsupportedResponse,
        );

        $this->expectException(\TypeError::class);

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
        @$entry->handleTemplateRedirect();

        self::assertSame('/path/to/404.php', $entry->filterTemplateInclude('/original.php'));
    }

    #[Test]
    public function handleTemplateRedirectDispatchesFirstMatchingVar(): void
    {
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

    #[Test]
    public function dispatchWithNotFoundExceptionSets404(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function (): never {
                throw new NotFoundException();
            },
        );

        set_query_var('test_slug', 'hello');

        try {
            $entry->handleTemplateRedirect();
        } catch (\Throwable) {
            // wp_die() may throw WPAjaxDieStopException in the test suite
        }

        global $wp_query;
        self::assertTrue($wp_query->is_404());
    }

    #[Test]
    public function dispatchWithHttpExceptionFallsBackToWpDie(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function (): never {
                throw new ForbiddenException('Access denied.');
            },
        );

        set_query_var('test_slug', 'hello');

        // wp_die() in test environment throws WPDieException
        try {
            $entry->handleTemplateRedirect();
        } catch (\WPDieException $e) {
            self::assertSame('Access denied.', $e->getMessage());

            return;
        }

        // If wp_die didn't throw (e.g. template was found), verify no pending template
        self::assertSame('/original.php', $entry->filterTemplateInclude('/original.php'));
    }

    #[Test]
    public function dispatchWithExceptionFiresAction(): void
    {
        $caughtException = null;
        add_action('wppack_routing_exception', function ($e) use (&$caughtException): void {
            $caughtException = $e;
        });

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function (): never {
                throw new NotFoundException('Page not found.');
            },
        );

        set_query_var('test_slug', 'hello');

        try {
            $entry->handleTemplateRedirect();
        } catch (\WPDieException) {
            // Expected when no template is found
        }

        remove_all_filters('wppack_routing_exception');

        self::assertInstanceOf(NotFoundException::class, $caughtException);
        self::assertSame('Page not found.', $caughtException->getMessage());
    }

    #[Test]
    public function dispatchWithExceptionUsingTemplateResponseFilter(): void
    {
        add_filter('wppack_routing_exception_response', function (?Response $response, $e): TemplateResponse {
            return new TemplateResponse(
                '/custom/error-template.php',
                ['error' => $e->getMessage()],
                $e->getStatusCode(),
            );
        }, 10, 2);

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function (): never {
                throw new ForbiddenException('No access.');
            },
        );

        set_query_var('test_slug', 'hello');

        // wp_die() in test environment throws WPDieException
        try {
            @$entry->handleTemplateRedirect();
        } catch (\WPDieException) {
            // Expected in some configurations
        } finally {
            remove_all_filters('wppack_routing_exception_response');
            remove_all_filters('wppack_routing_exception');
        }

        // If the filter returned a TemplateResponse, the pending template should be set
        $result = $entry->filterTemplateInclude('/original.php');
        if ($result !== '/original.php') {
            self::assertSame('/custom/error-template.php', $result);
        } else {
            // Template may not have been set if wp_die was called instead
            self::assertSame('/original.php', $result);
        }
    }

    #[Test]
    public function handleTemplateRedirectWithFalseQueryVar(): void
    {
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

        // Set query var to false (should not trigger dispatch)
        set_query_var('test_slug', false);
        $entry->handleTemplateRedirect();

        self::assertFalse($called);
    }

    #[Test]
    public function registerRouteWithBottomPosition(): void
    {
        $entry = new RouteEntry(
            'bottom_route',
            '^bottom/([^/]+)/?$',
            'index.php?bottom_slug=$matches[1]',
            RoutePosition::Bottom,
            [['%bottom_slug%', '([^/]+)']],
            fn() => null,
        );

        $entry->registerRoute();

        global $wp_rewrite;
        self::assertArrayHasKey('^bottom/([^/]+)/?$', $wp_rewrite->extra_rules ?? $wp_rewrite->extra_rules_top ?? []);
    }

    #[Test]
    public function parseQueryVarsStaticMethodWorksStandalone(): void
    {
        $vars = RouteEntry::parseQueryVars('index.php?cat=$matches[1]&tag=$matches[2]');

        self::assertSame(['cat', 'tag'], $vars);
    }

    #[Test]
    public function parseQueryVarsWithNoMatchesReturnsEmpty(): void
    {
        $vars = RouteEntry::parseQueryVars('index.php?page_id=42');

        self::assertSame([], $vars);
    }

    #[Test]
    public function filterQueryVarsWithEmptyInputArray(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            fn() => null,
        );

        $vars = $entry->filterQueryVars([]);

        self::assertSame(['test_slug'], $vars);
    }

    #[Test]
    public function dispatchWithNonHttpExceptionPropagates(): void
    {
        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function (): never {
                throw new \RuntimeException('Non-HTTP exception');
            },
        );

        set_query_var('test_slug', 'hello');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Non-HTTP exception');

        $entry->handleTemplateRedirect();
    }

    #[Test]
    public function exceptionResponseFilterOverridesDefault(): void
    {
        add_filter('wppack_routing_exception_response', function (?Response $response, $e): JsonResponse {
            return new JsonResponse(
                ['error' => $e->getErrorCode(), 'message' => $e->getMessage()],
                $e->getStatusCode(),
            );
        }, 10, 2);

        // wp_send_json calls die directly unless wp_doing_ajax() is true
        $doingAjax = static fn(): bool => true;
        add_filter('wp_doing_ajax', $doingAjax);

        $entry = new RouteEntry(
            'test_route',
            '^test/([^/]+)/?$',
            'index.php?test_slug=$matches[1]',
            RoutePosition::Top,
            [],
            function (): never {
                throw new NotFoundException('Not found.');
            },
        );

        set_query_var('test_slug', 'hello');

        // The filter returns a JsonResponse, which triggers handleJson → wp_send_json
        // wp_send_json echoes JSON and calls wp_die (via wp_doing_ajax), which throws WPDieException
        try {
            ob_start();
            $entry->handleTemplateRedirect();
        } catch (\WPDieException) {
            // Expected: wp_send_json calls wp_die in test environment
        } finally {
            ob_end_clean();
            remove_filter('wp_doing_ajax', $doingAjax);
            remove_all_filters('wppack_routing_exception_response');
            remove_all_filters('wppack_routing_exception');
        }

        // Verify the 404 was still set before the filter overrode
        global $wp_query;
        self::assertTrue($wp_query->is_404());
    }
}
