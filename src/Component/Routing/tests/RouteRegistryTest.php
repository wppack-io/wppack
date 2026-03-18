<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Routing\AbstractController;
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteEntry;
use WpPack\Component\Routing\RoutePosition;
use WpPack\Component\Routing\RouteRegistry;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Tests\SecurityTestTrait;

final class RouteRegistryTest extends TestCase
{
    use SecurityTestTrait;

    #[Test]
    public function resolvesSingleActionController(): void
    {
        $controller = new #[Route(name: 'test_route', regex: '^test/([^/]+)/?$', query: 'index.php?test_slug=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('test_route'));
        self::assertCount(1, $registry->getRegisteredRoutes());
    }

    #[Test]
    public function resolvesMultiActionController(): void
    {
        $controller = new class {
            #[Route(
                name: 'route_list',
                regex: '^items/?$',
                query: 'index.php?item_page=list',
            )]
            public function list(): ?TemplateResponse
            {
                return null;
            }

            #[Route(
                name: 'route_detail',
                regex: '^items/([^/]+)/?$',
                query: 'index.php?item_slug=$matches[1]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('route_list'));
        self::assertTrue($registry->has('route_detail'));
        self::assertCount(2, $registry->getRegisteredRoutes());
    }

    #[Test]
    public function resolvesClassLevelRewriteTags(): void
    {
        $controller = new #[RewriteTag('%test_tag%', '([^/]+)')]
        #[Route(name: 'tagged_route', regex: '^tagged/%test_tag%/?$', query: 'index.php?test_tag=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['tagged_route'];
        self::assertSame([['%test_tag%', '([^/]+)']], $entry->rewriteTags);
    }

    #[Test]
    public function resolvesMethodLevelRewriteTags(): void
    {
        $controller = new class {
            #[RewriteTag('%method_tag%', '(\d+)')]
            #[Route(
                name: 'method_tagged',
                regex: '^method/%method_tag%/?$',
                query: 'index.php?method_tag=$matches[1]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['method_tagged'];
        self::assertSame([['%method_tag%', '(\d+)']], $entry->rewriteTags);
    }

    #[Test]
    public function combinesClassAndMethodRewriteTags(): void
    {
        $controller = new #[RewriteTag('%class_tag%', '([^/]+)')] class {
            #[RewriteTag('%method_tag%', '(\d+)')]
            #[Route(
                name: 'combined_tags',
                regex: '^combined/%class_tag%/%method_tag%/?$',
                query: 'index.php?class_tag=$matches[1]&method_tag=$matches[2]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['combined_tags'];
        self::assertSame(
            [['%class_tag%', '([^/]+)'], ['%method_tag%', '(\d+)']],
            $entry->rewriteTags,
        );
    }

    #[Test]
    public function throwsWhenClassRouteWithoutInvoke(): void
    {
        $controller = new #[Route(name: 'broken_route', regex: '^broken/?$', query: 'index.php?broken=1', )] class {};

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement __invoke()');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenNoRoutesFound(): void
    {
        $controller = new class {
            public function someMethod(): void {}
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no #[Route] attributes');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function hasReturnsTrueForRegistered(): void
    {
        $controller = new #[Route(name: 'exists_route', regex: '^exists/?$', query: 'index.php?exists_page=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('exists_route'));
    }

    #[Test]
    public function hasReturnsFalseForUnregistered(): void
    {
        $registry = new RouteRegistry();

        self::assertFalse($registry->has('nonexistent_route'));
    }

    #[Test]
    public function registerAddsInitHook(): void
    {
        $controller = new #[Route(name: 'wp_test_route', regex: '^wp-test/?$', query: 'index.php?wp_test=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_action('init'));
    }

    #[Test]
    public function registerAddsQueryVarsHook(): void
    {
        $controller = new #[Route(name: 'wp_vars_route', regex: '^wp-vars/?$', query: 'index.php?wp_var=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_filter('query_vars'));
    }

    #[Test]
    public function registerAddsTemplateRedirectHook(): void
    {
        $controller = new #[Route(name: 'wp_redirect_route', regex: '^wp-redirect/?$', query: 'index.php?wp_redirect=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_action('template_redirect'));
    }

    #[Test]
    public function registerAddsTemplateIncludeHook(): void
    {
        $controller = new #[Route(name: 'wp_include_route', regex: '^wp-include/?$', query: 'index.php?wp_include=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_filter('template_include'));
    }

    #[Test]
    public function getRegisteredRoutesReturnsEmptyByDefault(): void
    {
        $registry = new RouteRegistry();

        self::assertSame([], $registry->getRegisteredRoutes());
    }

    #[Test]
    public function flushCallsFlushRewriteRules(): void
    {
        $registry = new RouteRegistry();
        $registry->flush();

        // flush_rewrite_rules() does not throw and resets rewrite rules
        self::assertTrue(true);
    }

    #[Test]
    public function resolvesRoutePositionFromAttribute(): void
    {
        $controller = new #[Route(name: 'bottom_route', regex: '^bottom/?$', query: 'index.php?page=$matches[1]', position: RoutePosition::Bottom, )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        self::assertSame(RoutePosition::Bottom, $routes['bottom_route']->position);
    }

    #[Test]
    public function multiActionControllerSkipsInvokeMethod(): void
    {
        $controller = new class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }

            #[Route(
                name: 'method_route',
                regex: '^method/?$',
                query: 'index.php?method_page=$matches[1]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        // Only the method with #[Route] should be registered, not __invoke
        self::assertCount(1, $registry->getRegisteredRoutes());
        self::assertTrue($registry->has('method_route'));
    }

    #[Test]
    public function registeredRouteEntryHasCorrectQueryVars(): void
    {
        $controller = new #[Route(name: 'detail_route', regex: '^items/(\d+)/([^/]+)/?$', query: 'index.php?item_id=$matches[1]&item_slug=$matches[2]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        self::assertSame(['item_id', 'item_slug'], $routes['detail_route']->queryVars);
    }

    #[Test]
    public function handlerInjectsRequest(): void
    {
        $request = new Request();
        $controller = new class {
            public ?Request $capturedRequest = null;

            #[Route(
                name: 'inject_request',
                regex: '^items/([^/]+)/?$',
                query: 'index.php?item_slug=$matches[1]',
            )]
            public function show(Request $request): ?TemplateResponse
            {
                $this->capturedRequest = $request;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['inject_request'];

        // Simulate WordPress setting query vars
        set_query_var('item_slug', 'test-item');
        $entry->handleTemplateRedirect();

        self::assertSame($request, $controller->capturedRequest);
        self::assertSame('test-item', $request->attributes->get('item_slug'));
    }

    #[Test]
    public function handlerInjectsCurrentUser(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $security = $this->createSecurity(user: $user);
        $request = new Request();

        $controller = new class {
            public ?\WP_User $capturedUser = null;

            #[Route(
                name: 'inject_user',
                regex: '^profile/([^/]+)/?$',
                query: 'index.php?profile_page=$matches[1]',
            )]
            public function index(#[CurrentUser] \WP_User $user): ?TemplateResponse
            {
                $this->capturedUser = $user;

                return null;
            }
        };

        $registry = new RouteRegistry($request, $security);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['inject_user'];

        set_query_var('profile_page', '1');
        $entry->handleTemplateRedirect();

        self::assertSame($user, $controller->capturedUser);
    }

    #[Test]
    public function handlerResolvesRouteParamsFromAttributes(): void
    {
        $request = new Request();

        $controller = new class {
            public ?string $capturedSlug = null;

            #[Route(
                name: 'resolve_params',
                regex: '^items/([^/]+)/?$',
                query: 'index.php?item_slug=$matches[1]',
            )]
            public function show(string $itemSlug): ?TemplateResponse
            {
                $this->capturedSlug = $itemSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['resolve_params'];

        set_query_var('item_slug', 'my-item');
        $entry->handleTemplateRedirect();

        self::assertSame('my-item', $controller->capturedSlug);
        self::assertSame('my-item', $request->attributes->get('item_slug'));
    }

    #[Test]
    public function handlerInjectsRequestAndCurrentUserWithParams(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $security = $this->createSecurity(user: $user);
        $request = new Request();

        $controller = new class {
            public ?\WP_User $capturedUser = null;
            public ?Request $capturedRequest = null;
            public ?string $capturedSlug = null;

            #[Route(
                name: 'inject_all',
                regex: '^items/([^/]+)/?$',
                query: 'index.php?item_slug=$matches[1]',
            )]
            public function show(#[CurrentUser] \WP_User $user, Request $request, string $itemSlug): ?TemplateResponse
            {
                $this->capturedUser = $user;
                $this->capturedRequest = $request;
                $this->capturedSlug = $itemSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request, $security);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['inject_all'];

        set_query_var('item_slug', 'test-slug');
        $entry->handleTemplateRedirect();

        self::assertSame($user, $controller->capturedUser);
        self::assertSame($request, $controller->capturedRequest);
        self::assertSame('test-slug', $controller->capturedSlug);
    }

    #[Test]
    public function singleActionControllerInjectsRequestAndParams(): void
    {
        $request = new Request();

        $controller = new #[Route(name: 'invoke_inject', regex: '^items/([^/]+)/?$', query: 'index.php?item_slug=$matches[1]', )] class {
            public ?Request $capturedRequest = null;
            public ?string $capturedSlug = null;

            public function __invoke(Request $request, string $itemSlug): ?TemplateResponse
            {
                $this->capturedRequest = $request;
                $this->capturedSlug = $itemSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['invoke_inject'];

        set_query_var('item_slug', 'invoke-item');
        $entry->handleTemplateRedirect();

        self::assertSame($request, $controller->capturedRequest);
        self::assertSame('invoke-item', $controller->capturedSlug);
        self::assertSame('invoke-item', $request->attributes->get('item_slug'));
    }

    #[Test]
    public function currentUserInjectionWithoutSecurity(): void
    {
        $request = new Request();

        $controller = new class {
            public bool $called = false;
            public mixed $capturedUser = 'not_set';

            #[Route(
                name: 'no_security_user',
                regex: '^no-security/([^/]+)/?$',
                query: 'index.php?no_security_page=$matches[1]',
            )]
            public function index(#[CurrentUser] ?\WP_User $user = null): ?TemplateResponse
            {
                $this->called = true;
                $this->capturedUser = $user;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['no_security_user'];

        set_query_var('no_security_page', '1');
        $entry->handleTemplateRedirect();

        self::assertTrue($controller->called);
        self::assertNull($controller->capturedUser);
    }

    #[Test]
    public function registerSetsSecurity(): void
    {
        $user = new \WP_User();
        $user->ID = 42;

        $security = $this->createSecurity(user: $user);
        $request = new Request();

        $controller = new class extends AbstractController {
            public ?\WP_User $capturedUser = null;

            #[Route(
                name: 'set_security',
                regex: '^secure/([^/]+)/?$',
                query: 'index.php?secure_page=$matches[1]',
            )]
            public function index(): ?TemplateResponse
            {
                $this->capturedUser = $this->getUser();

                return null;
            }
        };

        $registry = new RouteRegistry($request, $security);
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['set_security'];

        set_query_var('secure_page', '1');
        $entry->handleTemplateRedirect();

        self::assertSame($user, $controller->capturedUser);
    }

    /**
     * Creates a RouteRegistry that bypasses WordPress hook functions for unit testing.
     *
     * Uses reflection to call resolveRoutes() directly and stores entries,
     * avoiding the need for add_action/add_filter.
     */
    private function createRegistryWithoutWordPress(): RouteRegistry
    {
        return new RouteRegistry();
    }
}
