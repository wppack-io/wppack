<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\Exception\RouteNotFoundException;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Security\Tests\SecurityTestTrait;

final class RestRegistryTest extends TestCase
{
    use SecurityTestTrait;

    protected function tearDown(): void
    {
        global $wp_rest_server;
        $wp_rest_server = null;
        remove_all_actions('rest_api_init');

        parent::tearDown();
    }

    private function createRegistryWithoutWordPress(): RestRegistry
    {
        return new RestRegistry(new Request());
    }

    #[Test]
    public function resolvesControllerWithSingleRoute(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries);
        self::assertSame('test/v1', $entries[0]->namespace);
        self::assertSame('/items', $entries[0]->route);
        self::assertSame(['GET'], $entries[0]->methods);
    }

    #[Test]
    public function resolvesControllerWithMultipleRoutes(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }

            #[RestRoute('/(?P<id>\d+)', methods: HttpMethod::GET)]
            public function show(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(2, $entries);
        self::assertSame('/items', $entries[0]->route);
        self::assertSame('/items/(?P<id>\d+)', $entries[1]->route);
    }

    #[Test]
    public function resolvesClassLevelIsGranted(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[IsGranted('edit_posts')] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries[0]->isGrantedAttributes);
        self::assertSame('edit_posts', $entries[0]->isGrantedAttributes[0]->attribute);
    }

    #[Test]
    public function methodLevelPermissionOverridesClassLevel(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }

            #[RestRoute(methods: HttpMethod::POST)]
            #[IsGranted('edit_posts')]
            public function create(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertTrue($entries[0]->permission->public);
        self::assertCount(0, $entries[0]->isGrantedAttributes);
        self::assertCount(1, $entries[1]->isGrantedAttributes);
        self::assertSame('edit_posts', $entries[1]->isGrantedAttributes[0]->attribute);
    }

    #[Test]
    public function combinesClassRouteWithMethodRoute(): void
    {
        $controller = new #[RestRoute('/products', namespace: 'shop/v1')] #[Permission(public: true)] class {
            #[RestRoute('/(?P<id>\d+)/reviews', methods: HttpMethod::GET)]
            public function reviews(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('/products/(?P<id>\d+)/reviews', $entries[0]->route);
        self::assertSame('shop/v1', $entries[0]->namespace);
    }

    #[Test]
    public function resolvesParameterLevelParamAttributes(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(
                #[Param(minimum: 1, maximum: 100)]
                int $perPage = 10,
            ): array {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('per_page', $entries[0]->params[0]->name);
        self::assertSame('integer', $entries[0]->params[0]->type);
        self::assertFalse($entries[0]->params[0]->required);
        self::assertSame(10, $entries[0]->params[0]->default);
        self::assertSame(1, $entries[0]->params[0]->param->minimum);
        self::assertSame(100, $entries[0]->params[0]->param->maximum);
    }

    #[Test]
    public function infersParamNameTypeRequiredFromPhp(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::POST)]
            public function create(string $title, bool $published = false): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        $params = $entries[0]->params;

        self::assertCount(2, $params);

        self::assertSame('title', $params[0]->name);
        self::assertSame('string', $params[0]->type);
        self::assertTrue($params[0]->required);
        self::assertNull($params[0]->default);

        self::assertSame('published', $params[1]->name);
        self::assertSame('boolean', $params[1]->type);
        self::assertFalse($params[1]->required);
        self::assertFalse($params[1]->default);
    }

    #[Test]
    public function skipsRequestParameter(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::POST)]
            public function create(string $title, \WpPack\Component\HttpFoundation\Request $request): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('title', $entries[0]->params[0]->name);
    }

    #[Test]
    public function throwsWhenNoClassLevelRoute(): void
    {
        $controller = new class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a #[RestRoute] attribute');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenClassRouteHasNoNamespace(): void
    {
        $controller = new #[RestRoute('/items')] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must specify a namespace');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenNoMethodRoutes(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] class {
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no methods with #[RestRoute] attributes');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function repeatableRouteCreatesMultipleEntries(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::PUT)]
            #[RestRoute(methods: HttpMethod::PATCH)]
            public function update(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(2, $entries);
        self::assertSame(['PUT'], $entries[0]->methods);
        self::assertSame(['PATCH'], $entries[1]->methods);
    }

    #[Test]
    public function registerAddsRestApiInitHook(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        self::assertNotFalse(has_action('rest_api_init'));
    }

    #[Test]
    public function allReturnsEmptyByDefault(): void
    {
        $registry = new RestRegistry(new Request());

        self::assertSame([], $registry->all());
    }

    #[Test]
    public function resolvesFloatParameterAsNumberType(): void
    {
        $controller = new #[RestRoute('/metrics', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::POST)]
            public function store(float $score): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('number', $entries[0]->params[0]->type);
    }

    #[Test]
    public function resolvesArrayParameterAsArrayType(): void
    {
        $controller = new #[RestRoute('/batch', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::POST)]
            public function process(array $ids): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('array', $entries[0]->params[0]->type);
    }

    #[Test]
    public function skipWpRestRequestParameter(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(\WP_REST_Request $request, int $page = 1): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('page', $entries[0]->params[0]->name);
    }

    #[Test]
    public function convertsCamelCaseToSnakeCase(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function list(int $perPage = 10, string $sortOrder = 'asc'): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('per_page', $entries[0]->params[0]->name);
        self::assertSame('sort_order', $entries[0]->params[1]->name);
    }

    #[Test]
    public function permissionCallbackIsReturnTrueForPublic(): void
    {
        $controller = new #[RestRoute('/public', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function index(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/public'][0];
        self::assertSame('__return_true', $route['permission_callback']);
    }

    #[Test]
    public function permissionCallbackChecksIsGranted(): void
    {
        $controller = new #[RestRoute('/admin', namespace: 'test/v1')] #[IsGranted('manage_options')] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function index(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/admin'][0];
        self::assertIsCallable($route['permission_callback']);
    }

    #[Test]
    public function callbackExtractsParamsFromRequest(): void
    {
        $capturedId = null;
        $capturedTitle = null;
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?int $capturedId = null;
            public ?string $capturedTitle = null;

            #[RestRoute('/(?P<id>\d+)', methods: HttpMethod::PUT)]
            public function update(int $id, string $title): array
            {
                $this->capturedId = $id;
                $this->capturedTitle = $title;

                return ['id' => $id, 'title' => $title];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $request = new \WP_REST_Request('PUT', '/test/v1/items/42');
        $request->set_param('id', 42);
        $request->set_param('title', 'Updated');

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/items/(?P<id>\d+)'][0];
        $result = call_user_func($route['callback'], $request);

        self::assertInstanceOf(\WP_REST_Response::class, $result);
        self::assertSame(42, $controller->capturedId);
        self::assertSame('Updated', $controller->capturedTitle);
    }

    #[Test]
    public function callbackInjectsCustomRequestObject(): void
    {
        $controller = new #[RestRoute('/inject-request/(?P<id>\d+)', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?\WpPack\Component\HttpFoundation\Request $capturedRequest = null;

            #[RestRoute(methods: HttpMethod::GET)]
            public function index(\WpPack\Component\HttpFoundation\Request $request): array
            {
                $this->capturedRequest = $request;

                return [];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/inject-request/42');
        $wpRequest->set_url_params(['id' => '42']);
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/inject-request/(?P<id>\d+)'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertInstanceOf(Request::class, $controller->capturedRequest);
        self::assertSame('42', $controller->capturedRequest->attributes->get('id'));
    }

    #[Test]
    public function createArgsIncludesValidateCallback(): void
    {
        $controller = new #[RestRoute('/validate-items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::POST)]
            public function create(
                #[Param(validate: 'validateTitle')]
                string $title,
            ): array {
                return [];
            }

            public function validateTitle(mixed $value, \WP_REST_Request $request, string $key): bool
            {
                return is_string($value) && strlen($value) >= 3;
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/validate-items'][0];
        self::assertArrayHasKey('title', $route['args']);
        self::assertArrayHasKey('validate_callback', $route['args']['title']);
        self::assertIsCallable($route['args']['title']['validate_callback']);
    }

    #[Test]
    public function createArgsIncludesSanitizeCallback(): void
    {
        $controller = new #[RestRoute('/sanitize-items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::POST)]
            public function create(
                #[Param(sanitize: 'sanitizeTitle')]
                string $title,
            ): array {
                return [];
            }

            public function sanitizeTitle(mixed $value, \WP_REST_Request $request, string $key): string
            {
                return trim((string) $value);
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/sanitize-items'][0];
        self::assertArrayHasKey('title', $route['args']);
        self::assertArrayHasKey('sanitize_callback', $route['args']['title']);
        self::assertIsCallable($route['args']['title']['sanitize_callback']);
    }

    #[Test]
    public function permissionCallbackInvokesControllerMethod(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] class {
            #[RestRoute(methods: HttpMethod::GET)]
            #[Permission(callback: 'checkAccess')]
            public function index(): array
            {
                return [];
            }

            public function checkAccess(\WP_REST_Request $request): bool
            {
                return true;
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/items'][0];
        self::assertIsCallable($route['permission_callback']);

        $request = new \WP_REST_Request('GET', '/test/v1/items');
        $result = call_user_func($route['permission_callback'], $request);
        self::assertTrue($result);
    }

    #[Test]
    public function emptyMethodRouteProducesSlash(): void
    {
        $controller = new #[RestRoute('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function index(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('/items', $entries[0]->route);
    }

    #[Test]
    public function callbackInjectsCurrentUser(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $security = $this->createSecurity(user: $user);

        $controller = new #[RestRoute('/current-user', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?\WP_User $capturedUser = null;

            #[RestRoute(methods: HttpMethod::GET)]
            public function index(#[CurrentUser] \WP_User $user): array
            {
                $this->capturedUser = $user;

                return [];
            }
        };

        $registry = new RestRegistry(new Request(), $security);
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/current-user');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/current-user'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertSame($user, $controller->capturedUser);
    }

    #[Test]
    public function currentUserParamIsExcludedFromRestParams(): void
    {
        $security = $this->createSecurity();

        $controller = new #[RestRoute('/current-user-params', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: HttpMethod::GET)]
            public function index(#[CurrentUser] \WP_User $user, int $page = 1): array
            {
                return [];
            }
        };

        $registry = new RestRegistry(new Request(), $security);
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('page', $entries[0]->params[0]->name);
    }

    #[Test]
    public function currentUserInjectionWithoutSecurity(): void
    {
        $controller = new #[RestRoute('/no-security-user', namespace: 'test/v1')] #[Permission(public: true)] class {
            public bool $called = false;
            public mixed $capturedUser = 'not_set';

            #[RestRoute(methods: HttpMethod::GET)]
            public function index(#[CurrentUser] ?\WP_User $user = null): array
            {
                $this->called = true;
                $this->capturedUser = $user;

                return [];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/no-security-user');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/no-security-user'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertTrue($controller->called);
        self::assertNull($controller->capturedUser);
    }

    #[Test]
    public function callbackInjectsCurrentUserBeforeRequest(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $security = $this->createSecurity(user: $user);

        $controller = new #[RestRoute('/cu-req', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?\WP_User $capturedUser = null;
            public ?Request $capturedRequest = null;
            public ?int $capturedId = null;

            #[RestRoute('/(?P<id>\d+)', methods: HttpMethod::GET)]
            public function show(#[CurrentUser] \WP_User $user, Request $request, int $id): array
            {
                $this->capturedUser = $user;
                $this->capturedRequest = $request;
                $this->capturedId = $id;

                return [];
            }
        };

        $registry = new RestRegistry(new Request(), $security);
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/cu-req/99');
        $wpRequest->set_url_params(['id' => 99]);
        $wpRequest->set_param('id', 99);
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/cu-req/(?P<id>\d+)'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertSame($user, $controller->capturedUser);
        self::assertInstanceOf(Request::class, $controller->capturedRequest);
        self::assertSame(99, $controller->capturedRequest->attributes->get('id'));
        self::assertSame(99, $controller->capturedId);
    }

    #[Test]
    public function callbackInjectsCurrentUserAtMiddlePosition(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $security = $this->createSecurity(user: $user);

        $controller = new #[RestRoute('/cu-mid', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?int $capturedId = null;
            public ?\WP_User $capturedUser = null;
            public ?string $capturedName = null;

            #[RestRoute('/(?P<id>\d+)', methods: HttpMethod::GET)]
            public function show(int $id, #[CurrentUser] \WP_User $user, string $name): array
            {
                $this->capturedId = $id;
                $this->capturedUser = $user;
                $this->capturedName = $name;

                return [];
            }
        };

        $registry = new RestRegistry(new Request(), $security);
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/cu-mid/7');
        $wpRequest->set_param('id', 7);
        $wpRequest->set_param('name', 'test-item');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/cu-mid/(?P<id>\d+)'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertSame(7, $controller->capturedId);
        self::assertSame($user, $controller->capturedUser);
        self::assertSame('test-item', $controller->capturedName);
    }

    #[Test]
    public function registerSetsSecurity(): void
    {
        $user = new \WP_User();
        $user->ID = 42;

        $security = $this->createSecurity(user: $user);

        $controller = new #[RestRoute('/set-security', namespace: 'test/v1')] #[Permission(public: true)] class extends AbstractRestController {
            public ?\WP_User $capturedUser = null;

            #[RestRoute(methods: HttpMethod::GET)]
            public function index(): array
            {
                $this->capturedUser = $this->getUser();

                return [];
            }
        };

        $registry = new RestRegistry(new Request(), $security);
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/set-security');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/set-security'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertSame($user, $controller->capturedUser);
    }

    #[Test]
    public function resolvesInvokeController(): void
    {
        $controller = new #[RestRoute('/invoke-items', namespace: 'test/v1', methods: [HttpMethod::GET])] #[Permission(public: true)] class {
            public function __invoke(): array
            {
                return ['invoked' => true];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(1, $entries);
        self::assertSame('test/v1', $entries[0]->namespace);
        self::assertSame('/invoke-items', $entries[0]->route);
        self::assertSame(['GET'], $entries[0]->methods);
    }

    #[Test]
    public function invokeControllerWithPathParams(): void
    {
        $controller = new #[RestRoute('/invoke-products/{id}', namespace: 'test/v1', methods: [HttpMethod::GET])] #[Permission(public: true)] class {
            public ?int $capturedId = null;

            public function __invoke(int $id): array
            {
                $this->capturedId = $id;

                return ['id' => $id];
            }
        };

        $registry = new RestRegistry(new Request());
        $registry->register($controller);

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/invoke-products/42');
        $wpRequest->set_param('id', 42);

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/invoke-products/(?P<id>[^/]+)'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertSame(42, $controller->capturedId);
    }

    #[Test]
    public function throwsWhenClassRouteHasMethodsButNoInvoke(): void
    {
        $controller = new #[RestRoute('/no-invoke', namespace: 'test/v1', methods: [HttpMethod::GET])] #[Permission(public: true)] class {
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement __invoke()');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function invokeWithMethodRoutesCombined(): void
    {
        $controller = new #[RestRoute('/combo', namespace: 'test/v1', methods: [HttpMethod::GET])] #[Permission(public: true)] class {
            public function __invoke(): array
            {
                return ['index' => true];
            }

            #[RestRoute('/detail', methods: [HttpMethod::GET])]
            public function detail(): array
            {
                return ['detail' => true];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(2, $entries);
        self::assertSame('/combo', $entries[0]->route);
        self::assertSame('/combo/detail', $entries[1]->route);
    }

    #[Test]
    public function pathBasedRouteCompiles(): void
    {
        $controller = new #[RestRoute('/products', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute('/{id}', methods: [HttpMethod::GET])]
            public function show(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('/products/(?P<id>[^/]+)', $entries[0]->route);
        self::assertSame('/products/{id}', $entries[0]->path);
    }

    #[Test]
    public function pathBasedRouteWithRequirements(): void
    {
        $controller = new #[RestRoute('/products', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute('/{id}', methods: [HttpMethod::GET], requirements: ['id' => '\d+'])]
            public function show(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('/products/(?P<id>\d+)', $entries[0]->route);
    }

    #[Test]
    public function pathBasedClassAndMethodRequirementsMerge(): void
    {
        $controller = new #[RestRoute('/categories/{category}', namespace: 'test/v1', requirements: ['category' => '[a-z]+'])] #[Permission(public: true)] class {
            #[RestRoute('/items/{id}', methods: [HttpMethod::GET], requirements: ['id' => '\d+'])]
            public function show(string $category, int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertSame('/categories/(?P<category>[a-z]+)/items/(?P<id>\d+)', $entries[0]->route);
    }

    #[Test]
    public function invokeSkippedInMethodScan(): void
    {
        $controller = new #[RestRoute('/skip-invoke', namespace: 'test/v1', methods: [HttpMethod::GET])] #[Permission(public: true)] class {
            public function __invoke(): array
            {
                return ['invoke' => true];
            }

            #[RestRoute('/extra', methods: [HttpMethod::POST])]
            public function extra(): array
            {
                return ['extra' => true];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->all();
        self::assertCount(2, $entries);
        self::assertSame('/skip-invoke', $entries[0]->route);
        self::assertSame(['GET'], $entries[0]->methods);
        self::assertSame('/skip-invoke/extra', $entries[1]->route);
        self::assertSame(['POST'], $entries[1]->methods);
    }

    #[Test]
    public function hasAndGetMethods(): void
    {
        $controller = new #[RestRoute('/named', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[RestRoute(methods: [HttpMethod::GET], name: 'named_list')]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('named_list'));
        self::assertFalse($registry->has('nonexistent'));

        $entry = $registry->get('named_list');
        self::assertSame('named_list', $entry->name);
        self::assertSame('/named', $entry->route);
    }

    #[Test]
    public function getThrowsRouteNotFoundException(): void
    {
        $registry = $this->createRegistryWithoutWordPress();

        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route "nonexistent" does not exist.');

        $registry->get('nonexistent');
    }
}
