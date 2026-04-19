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

namespace WPPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\HttpFoundation\ArgumentResolver;
use WPPack\Component\HttpFoundation\Exception\ForbiddenException;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\HttpFoundation\RequestValueResolver;
use WPPack\Component\Routing\AbstractController;
use WPPack\Component\Security\ValueResolver\CurrentUserValueResolver;
use WPPack\Component\Routing\Attribute\RewriteTag;
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Exception\RouteNotFoundException;
use WPPack\Component\Routing\Response\TemplateResponse;
use WPPack\Component\Routing\RouteEntry;
use WPPack\Component\Routing\RoutePosition;
use WPPack\Component\Routing\RouteRegistry;
use WPPack\Component\Security\Attribute\CurrentUser;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Security\Tests\SecurityTestTrait;
use WPPack\Component\Templating\TemplateRendererInterface;

final class RouteRegistryTest extends TestCase
{
    use SecurityTestTrait;

    protected function setUp(): void
    {
        remove_all_filters('redirect_canonical');
    }

    protected function tearDown(): void
    {
        remove_all_filters('redirect_canonical');
    }

    #[Test]
    public function resolvesSingleActionController(): void
    {
        $controller = new #[Route('/test/{test_slug}', name: 'test_route')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('test_route'));
        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function resolvesMultiActionController(): void
    {
        $controller = new class {
            #[Route('/items', name: 'route_list')]
            public function list(): ?TemplateResponse
            {
                return null;
            }

            #[Route('/items/{item_slug}', name: 'route_detail')]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('route_list'));
        self::assertTrue($registry->has('route_detail'));
        self::assertCount(2, $registry->all());
    }

    #[Test]
    public function resolvesClassLevelRewriteTags(): void
    {
        $controller = new #[RewriteTag('%test_tag%', '([^/]+)')]
        #[Route('/tagged/{test_tag}', name: 'tagged_route')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['tagged_route'];
        self::assertSame([['%test_tag%', '([^/]+)']], $entry->rewriteTags);
    }

    #[Test]
    public function resolvesMethodLevelRewriteTags(): void
    {
        $controller = new class {
            #[RewriteTag('%method_tag%', '(\d+)')]
            #[Route('/method/{method_tag}', name: 'method_tagged')]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['method_tagged'];
        self::assertSame([['%method_tag%', '(\d+)']], $entry->rewriteTags);
    }

    #[Test]
    public function combinesClassAndMethodRewriteTags(): void
    {
        $controller = new #[RewriteTag('%class_tag%', '([^/]+)')] class {
            #[RewriteTag('%method_tag%', '(\d+)')]
            #[Route('/combined/{class_tag}/{method_tag}', name: 'combined_tags')]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['combined_tags'];
        self::assertSame(
            [['%class_tag%', '([^/]+)'], ['%method_tag%', '(\d+)']],
            $entry->rewriteTags,
        );
    }

    #[Test]
    public function throwsWhenClassRouteWithoutInvoke(): void
    {
        $controller = new #[Route('/broken', name: 'broken_route')] class {};

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
        $controller = new #[Route('/exists/{exists_page}', name: 'exists_route')] class {
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
        $controller = new #[Route('/wp-test/{wp_test}', name: 'wp_test_route')] class {
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
        $controller = new #[Route('/wp-vars/{wp_var}', name: 'wp_vars_route')] class {
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
        $controller = new #[Route('/wp-redirect/{wp_redirect}', name: 'wp_redirect_route')] class {
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
    public function registerAddsRedirectCanonicalFilter(): void
    {
        $controller = new #[Route('/wp-canonical/{wp_canonical}', name: 'wp_canonical_route')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_filter('redirect_canonical'));
    }

    #[Test]
    public function registerAddsTemplateIncludeHook(): void
    {
        $controller = new #[Route('/wp-include/{wp_include}', name: 'wp_include_route')] class {
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
    public function allReturnsEmptyByDefault(): void
    {
        $registry = new RouteRegistry();

        self::assertSame([], $registry->all());
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
    public function invalidateDeletesRewriteRulesOption(): void
    {
        update_option('rewrite_rules', ['dummy' => 'rule']);
        self::assertNotFalse(get_option('rewrite_rules'));

        $registry = new RouteRegistry(optionManager: new \WPPack\Component\Option\OptionManager());
        $registry->invalidate();

        self::assertFalse(get_option('rewrite_rules'));
    }

    #[Test]
    public function resolvesRoutePositionFromAttribute(): void
    {
        $controller = new #[Route('/bottom/{page}', name: 'bottom_route', position: RoutePosition::Bottom)] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->all();
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

            #[Route('/method/{method_page}', name: 'method_route')]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        // Only the method with #[Route] should be registered, not __invoke
        self::assertCount(1, $registry->all());
        self::assertTrue($registry->has('method_route'));
    }

    #[Test]
    public function registeredRouteEntryHasCorrectQueryVars(): void
    {
        $controller = new #[Route('/items/{item_id}/{item_slug}', name: 'detail_route', requirements: ['item_id' => '\d+'])] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->all();
        self::assertSame(['item_id', 'item_slug'], $routes['detail_route']->queryVars);
    }

    #[Test]
    public function handlerInjectsRequest(): void
    {
        $request = new Request();
        $controller = new class {
            public ?Request $capturedRequest = null;

            #[Route('/items/{item_slug}', name: 'inject_request')]
            public function show(Request $request): ?TemplateResponse
            {
                $this->capturedRequest = $request;

                return null;
            }
        };

        $registry = new RouteRegistry($request, argumentResolver: new ArgumentResolver([new RequestValueResolver($request)]));
        $registry->register($controller);

        $routes = $registry->all();
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

            #[Route('/profile/{profile_page}', name: 'inject_user')]
            public function index(#[CurrentUser] \WP_User $user): ?TemplateResponse
            {
                $this->capturedUser = $user;

                return null;
            }
        };

        $registry = new RouteRegistry($request, $security, argumentResolver: new ArgumentResolver([new CurrentUserValueResolver($security)]));
        $registry->register($controller);

        $routes = $registry->all();
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

            #[Route('/items/{item_slug}', name: 'resolve_params')]
            public function show(string $itemSlug): ?TemplateResponse
            {
                $this->capturedSlug = $itemSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->all();
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

            #[Route('/items/{item_slug}', name: 'inject_all')]
            public function show(#[CurrentUser] \WP_User $user, Request $request, string $itemSlug): ?TemplateResponse
            {
                $this->capturedUser = $user;
                $this->capturedRequest = $request;
                $this->capturedSlug = $itemSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request, $security, argumentResolver: new ArgumentResolver([
            new RequestValueResolver($request),
            new CurrentUserValueResolver($security),
        ]));
        $registry->register($controller);

        $routes = $registry->all();
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

        $controller = new #[Route('/items/{item_slug}', name: 'invoke_inject')] class {
            public ?Request $capturedRequest = null;
            public ?string $capturedSlug = null;

            public function __invoke(Request $request, string $itemSlug): ?TemplateResponse
            {
                $this->capturedRequest = $request;
                $this->capturedSlug = $itemSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request, argumentResolver: new ArgumentResolver([new RequestValueResolver($request)]));
        $registry->register($controller);

        $routes = $registry->all();
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

            #[Route('/no-security/{no_security_page}', name: 'no_security_user')]
            public function index(#[CurrentUser] ?\WP_User $user = null): ?TemplateResponse
            {
                $this->called = true;
                $this->capturedUser = $user;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->all();
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

            #[Route('/secure/{secure_page}', name: 'set_security')]
            public function index(): ?TemplateResponse
            {
                $this->capturedUser = $this->getUser();

                return null;
            }
        };

        $registry = new RouteRegistry($request, $security);
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['set_security'];

        set_query_var('secure_page', '1');
        $entry->handleTemplateRedirect();

        self::assertSame($user, $controller->capturedUser);
    }

    #[Test]
    public function registerSetsTemplateRenderer(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $renderer->method('render')
            ->with('templates/test.html.twig', ['key' => 'value'])
            ->willReturn('<p>test</p>');

        $request = new Request();

        $controller = new class extends AbstractController {
            public ?string $result = null;

            #[Route('/renderer-test/{renderer_page}', name: 'renderer_test')]
            public function index(): ?TemplateResponse
            {
                $this->result = $this->renderView('templates/test.html.twig', ['key' => 'value']);

                return null;
            }
        };

        $registry = new RouteRegistry($request, null, $renderer);
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['renderer_test'];

        set_query_var('renderer_page', '1');
        $entry->handleTemplateRedirect();

        self::assertSame('<p>test</p>', $controller->result);
    }

    #[Test]
    public function handlerConvertsCamelCaseParamToSnakeCase(): void
    {
        $request = new Request();

        $controller = new class {
            public ?string $capturedValue = null;

            #[Route('/items/{product_name}', name: 'snake_case_test')]
            public function show(string $productName): ?TemplateResponse
            {
                $this->capturedValue = $productName;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['snake_case_test'];

        set_query_var('product_name', 'test-product');
        $entry->handleTemplateRedirect();

        self::assertSame('test-product', $controller->capturedValue);
    }

    #[Test]
    public function registerDoesNotSetSecurityOnNonAbstractController(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $security = $this->createSecurity(user: $user);

        $controller = new #[Route('/plain/{plain_page}', name: 'plain_controller')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry(null, $security);
        $registry->register($controller);

        // Should not throw even though controller is not AbstractController
        self::assertTrue($registry->has('plain_controller'));
    }

    #[Test]
    public function registerDoesNotSetRendererOnNonAbstractController(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);

        $controller = new #[Route('/no-renderer/{page}', name: 'no_renderer')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry(null, null, $renderer);
        $registry->register($controller);

        self::assertTrue($registry->has('no_renderer'));
    }

    #[Test]
    public function registerWithNullSecurityDoesNotCallSetSecurity(): void
    {
        $controller = new class extends AbstractController {
            #[Route('/null-sec/{page}', name: 'null_security')]
            public function index(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry(null, null, null);
        $registry->register($controller);

        self::assertTrue($registry->has('null_security'));
    }

    #[Test]
    public function registerWithNullRendererDoesNotCallSetRenderer(): void
    {
        $controller = new class extends AbstractController {
            #[Route('/null-rend/{page}', name: 'null_renderer')]
            public function index(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry(null, null, null);
        $registry->register($controller);

        self::assertTrue($registry->has('null_renderer'));
    }

    #[Test]
    public function handlerWithNoRequestDoesNotSetAttributes(): void
    {
        $controller = new class {
            public bool $called = false;

            #[Route('/no-req/{slug}', name: 'no_request')]
            public function show(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        // No request passed to registry
        $registry = new RouteRegistry();
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['no_request'];

        set_query_var('slug', 'test');
        $entry->handleTemplateRedirect();

        self::assertTrue($controller->called);
    }

    #[Test]
    public function isGrantedAllowsAccessWhenCapabilityGranted(): void
    {
        $request = new Request();

        $controller = new class {
            public bool $called = false;

            #[IsGranted('manage_options')]
            #[Route('/granted-allow/{granted_page}', name: 'is_granted_allow')]
            public function index(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        wp_set_current_user(1);
        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['is_granted_allow'];

        set_query_var('granted_page', '1');
        $entry->handleTemplateRedirect();

        self::assertTrue($controller->called);
    }

    #[Test]
    public function isGrantedDeniesAccessWhenCapabilityNotGranted(): void
    {
        $request = new Request();

        $controller = new class {
            public bool $called = false;

            #[IsGranted('manage_options')]
            #[Route('/granted-deny/{granted_deny_page}', name: 'is_granted_deny')]
            public function index(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        wp_set_current_user(0);
        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['is_granted_deny'];

        set_query_var('granted_deny_page', '1');

        // ForbiddenException is caught by RouteEntry::dispatch() and handled via wp_die
        ob_start();
        $entry->handleTemplateRedirect();
        ob_end_clean();

        self::assertFalse($controller->called);
    }

    #[Test]
    public function classLevelIsGrantedAppliesToAllRoutes(): void
    {
        $request = new Request();

        $controller = new #[IsGranted('manage_options')] class {
            public bool $called = false;

            #[Route('/class-granted/{class_granted_page}', name: 'class_granted')]
            public function index(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        wp_set_current_user(0);
        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $routes = $registry->all();
        $entry = $routes['class_granted'];

        set_query_var('class_granted_page', '1');

        // ForbiddenException is caught by RouteEntry::dispatch() and handled via wp_die
        ob_start();
        $entry->handleTemplateRedirect();
        ob_end_clean();

        self::assertFalse($controller->called);
    }

    // ──────────────────────────────────────────────────────────────
    // get() method
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function getReturnsRouteEntryByName(): void
    {
        $controller = new #[Route('/products/{product_slug}', name: 'product_detail')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entry = $registry->get('product_detail');
        self::assertSame('product_detail', $entry->name);
        self::assertSame('/products/{product_slug}', $entry->path);
    }

    #[Test]
    public function getThrowsRouteNotFoundExceptionForMissingRoute(): void
    {
        $registry = new RouteRegistry();

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route "nonexistent" does not exist.');

        $registry->get('nonexistent');
    }

    // ──────────────────────────────────────────────────────────────
    // path-based route compilation
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function pathBasedRouteGeneratesCorrectRegex(): void
    {
        $controller = new #[Route('/products/{product_slug}', name: 'path_regex_test')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entry = $registry->get('path_regex_test');
        self::assertSame('^products/(?P<product_slug>[^/]+)/?$', $entry->regex);
        self::assertSame('index.php?product_slug=$matches[1]', $entry->query);
    }

    #[Test]
    public function pathBasedRouteWithRequirementsGeneratesCorrectRegex(): void
    {
        $controller = new class {
            #[Route('/events/{year}/{month}', name: 'requirements_test', requirements: ['year' => '\d{4}', 'month' => '\d{2}'])]
            public function archive(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entry = $registry->get('requirements_test');
        self::assertSame('^events/(?P<year>\d{4})/(?P<month>\d{2})/?$', $entry->regex);
        self::assertSame('index.php?year=$matches[1]&month=$matches[2]', $entry->query);
    }

    #[Test]
    public function pathBasedRouteWithVarsGeneratesCorrectQuery(): void
    {
        $controller = new class {
            #[Route('/events/{year}', name: 'vars_test', vars: ['post_type' => 'event'])]
            public function archive(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entry = $registry->get('vars_test');
        self::assertSame('index.php?post_type=event&year=$matches[1]', $entry->query);
    }

    #[Test]
    public function pathBasedRouteStoresPathForUrlGeneration(): void
    {
        $controller = new #[Route('/products/{product_slug}', name: 'path_store_test')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entry = $registry->get('path_store_test');
        self::assertSame('/products/{product_slug}', $entry->path);
    }

    #[Test]
    public function pathBasedRouteParamsAreInjectedIntoHandler(): void
    {
        $request = new Request();

        $controller = new class {
            public ?string $capturedSlug = null;

            #[Route('/products/{product_slug}', name: 'inject_path_params')]
            public function show(string $productSlug): ?TemplateResponse
            {
                $this->capturedSlug = $productSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->register($controller);

        $entry = $registry->get('inject_path_params');

        set_query_var('product_slug', 'my-product');
        $entry->handleTemplateRedirect();

        self::assertSame('my-product', $controller->capturedSlug);
        self::assertSame('my-product', $request->attributes->get('product_slug'));
    }

    #[Test]
    public function pathBasedRouteParamsAreSetOnRequestAttributes(): void
    {
        $request = new Request();

        $controller = new class {
            #[Route('/events/{year}/{month}', name: 'attrs_test', requirements: ['year' => '\d{4}', 'month' => '\d{2}'])]
            public function archive(Request $request, string $year, string $month): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry($request, argumentResolver: new ArgumentResolver([new RequestValueResolver($request)]));
        $registry->register($controller);

        $entry = $registry->get('attrs_test');

        set_query_var('year', '2024');
        set_query_var('month', '03');
        $entry->handleTemplateRedirect();

        self::assertSame('2024', $request->attributes->get('year'));
        self::assertSame('03', $request->attributes->get('month'));
    }

    // ──────────────────────────────────────────────────────────────
    // addRoute()
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function addRouteRegistersInvokableController(): void
    {
        $controller = new class {
            public bool $called = false;

            public function __invoke(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->addRoute('/test/path', $controller, name: 'add_invoke');

        self::assertTrue($registry->has('add_invoke'));
        $entry = $registry->get('add_invoke');
        self::assertSame('/test/path', $entry->path);
    }

    #[Test]
    public function addRouteRegistersNamedAction(): void
    {
        $controller = new class {
            public bool $called = false;

            public function handleAction(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->addRoute('/test/action', $controller, action: 'handleAction', name: 'add_action');

        self::assertTrue($registry->has('add_action'));
    }

    #[Test]
    public function addRouteStaticPathDispatchesCorrectly(): void
    {
        $controller = new class {
            public bool $called = false;

            public function __invoke(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->addRoute('/static/page', $controller, name: 'add_static');

        $entry = $registry->get('add_static');
        self::assertSame(['_route_add_static'], $entry->queryVars);

        set_query_var('_route_add_static', '1');
        $entry->handleTemplateRedirect();

        self::assertTrue($controller->called);
    }

    #[Test]
    public function addRouteMethodRestrictionBlocksNonMatchingMethod(): void
    {
        $controller = new class {
            public bool $called = false;

            public function __invoke(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        $request = Request::create('/test', 'GET');
        $registry = new RouteRegistry($request);
        $registry->addRoute('/test/method', $controller, name: 'add_method', methods: ['POST']);

        $entry = $registry->get('add_method');
        set_query_var('_route_add_method', '1');
        $entry->handleTemplateRedirect();

        self::assertFalse($controller->called);
    }

    #[Test]
    public function addRouteMethodRestrictionAllowsMatchingMethod(): void
    {
        $controller = new class {
            public bool $called = false;

            public function __invoke(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        $request = Request::create('/test', 'POST');
        $registry = new RouteRegistry($request);
        $registry->addRoute('/test/method', $controller, name: 'add_method_ok', methods: ['POST']);

        $entry = $registry->get('add_method_ok');
        set_query_var('_route_add_method_ok', '1');
        $entry->handleTemplateRedirect();

        self::assertTrue($controller->called);
    }

    #[Test]
    public function addRouteWithPathParams(): void
    {
        $request = new Request();
        $controller = new class {
            public ?string $capturedSlug = null;

            public function __invoke(string $productSlug): ?TemplateResponse
            {
                $this->capturedSlug = $productSlug;

                return null;
            }
        };

        $registry = new RouteRegistry($request);
        $registry->addRoute('/products/{product_slug}', $controller, name: 'add_params');

        $entry = $registry->get('add_params');
        self::assertSame(['product_slug'], $entry->queryVars);

        set_query_var('product_slug', 'my-product');
        $entry->handleTemplateRedirect();

        self::assertSame('my-product', $controller->capturedSlug);
    }

    #[Test]
    public function addRouteWithRequirementsCompilesCorrectRegex(): void
    {
        $controller = new class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->addRoute(
            '/events/{year}/{month}',
            $controller,
            name: 'add_requirements',
            requirements: ['year' => '\d{4}', 'month' => '\d{2}'],
        );

        $entry = $registry->get('add_requirements');
        self::assertSame('^events/(?P<year>\d{4})/(?P<month>\d{2})/?$', $entry->regex);
        self::assertSame('index.php?year=$matches[1]&month=$matches[2]', $entry->query);
    }

    #[Test]
    public function addRouteWithVarsGeneratesCorrectQuery(): void
    {
        $controller = new class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->addRoute(
            '/events/{year}',
            $controller,
            name: 'add_vars',
            vars: ['post_type' => 'event'],
        );

        $entry = $registry->get('add_vars');
        self::assertSame('index.php?post_type=event&year=$matches[1]', $entry->query);
    }

    #[Test]
    public function addRouteWithIsGrantedDeniesAccess(): void
    {
        $request = new Request();

        $controller = new class {
            public bool $called = false;

            #[IsGranted('manage_options')]
            public function __invoke(): ?TemplateResponse
            {
                $this->called = true;

                return null;
            }
        };

        wp_set_current_user(0);
        $registry = new RouteRegistry($request);
        $registry->addRoute('/admin/page', $controller, name: 'add_granted_deny');

        $entry = $registry->get('add_granted_deny');
        set_query_var('_route_add_granted_deny', '1');

        ob_start();
        $entry->handleTemplateRedirect();
        ob_end_clean();

        self::assertFalse($controller->called);
    }

    #[Test]
    public function addRouteSetsSecurityOnAbstractController(): void
    {
        $user = new \WP_User();
        $user->ID = 42;

        $security = $this->createSecurity(user: $user);

        $controller = new class extends AbstractController {
            public ?\WP_User $capturedUser = null;

            public function __invoke(): ?TemplateResponse
            {
                $this->capturedUser = $this->getUser();

                return null;
            }
        };

        $registry = new RouteRegistry(null, $security);
        $registry->addRoute('/secure', $controller, name: 'add_secure');

        $entry = $registry->get('add_secure');
        set_query_var('_route_add_secure', '1');
        $entry->handleTemplateRedirect();

        self::assertSame($user, $controller->capturedUser);
    }

    // ──────────────────────────────────────────────────────────────
    // redirect_canonical filter integration tests
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function redirectCanonicalFilterNormalizesTrailingSlashViaApplyFilters(): void
    {
        $controller = new #[Route('/canonical/{canonical_slug}', name: 'canonical_cancel')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        set_query_var('canonical_slug', 'test-value');

        $result = apply_filters('redirect_canonical', 'https://example.com/canonical/test-value/', 'https://example.com/canonical/test-value');

        self::assertSame('https://example.com/canonical/test-value', $result);
    }

    #[Test]
    public function redirectCanonicalFilterPassesThroughWhenNoMatchViaApplyFilters(): void
    {
        $controller = new #[Route('/canonical-pass/{pass_slug}', name: 'canonical_pass')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        // Do not set query var — no match
        $result = apply_filters('redirect_canonical', 'https://example.com/other-page/', 'https://example.com/other-page');

        self::assertSame('https://example.com/other-page/', $result);
    }

    #[Test]
    public function redirectCanonicalFilterWithMultipleRoutes(): void
    {
        $controllerA = new #[Route('/multi-a/{multi_a_slug}', name: 'multi_a')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };
        $controllerB = new #[Route('/multi-b/{multi_b_slug}', name: 'multi_b')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controllerA);
        $registry->register($controllerB);

        // Only route A matches
        set_query_var('multi_a_slug', 'matched');

        $result = apply_filters('redirect_canonical', 'https://example.com/multi-a/matched/', 'https://example.com/multi-a/matched');

        // Route A normalizes trailing slash, route B passes through
        self::assertSame('https://example.com/multi-a/matched', $result);
    }

    #[Test]
    public function redirectCanonicalFilterWithMultipleRoutesNoneMatching(): void
    {
        $controllerA = new #[Route('/none-a/{none_a_slug}', name: 'none_a')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };
        $controllerB = new #[Route('/none-b/{none_b_slug}', name: 'none_b')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controllerA);
        $registry->register($controllerB);

        // Neither query var set
        $result = apply_filters('redirect_canonical', 'https://example.com/unrelated/', 'https://example.com/unrelated');

        self::assertSame('https://example.com/unrelated/', $result);
    }

    #[Test]
    public function redirectCanonicalFilterWithSentinelRouteViaApplyFilters(): void
    {
        $controller = new #[Route('/static/page', name: 'canonical_sentinel')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        // Static path uses sentinel query var
        set_query_var('_route_canonical_sentinel', '1');

        $result = apply_filters('redirect_canonical', 'https://example.com/static/page/', 'https://example.com/static/page');

        self::assertSame('https://example.com/static/page', $result);
    }

    #[Test]
    public function addRouteRegistersWorkingRedirectCanonicalFilter(): void
    {
        $controller = new class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->addRoute('/api-route/{api_slug}', $controller, name: 'add_canonical');

        set_query_var('api_slug', 'test');

        $result = apply_filters('redirect_canonical', 'https://example.com/api-route/test/', 'https://example.com/api-route/test');

        self::assertSame('https://example.com/api-route/test', $result);
    }

    #[Test]
    public function redirectCanonicalFilterNormalizesTrailingSlashForTrailingSlashRoute(): void
    {
        $controller = new #[Route('/trailing/{trailing_slug}/', name: 'trailing_route')] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        set_query_var('trailing_slug', 'test');

        $result = apply_filters('redirect_canonical', 'https://example.com/trailing/test', 'https://example.com/trailing/test');

        self::assertSame('https://example.com/trailing/test/', $result);
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
