<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\Route;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Rest\RestEntry;
use WpPack\Component\Rest\RestRegistry;

final class RestRegistryTest extends TestCase
{
    private function createRegistryWithoutWordPress(): RestRegistry
    {
        if (function_exists('add_action')) {
            return new RestRegistry();
        }

        return new class extends RestRegistry {
            public function register(object $controller): void
            {
                $reflection = new \ReflectionMethod(RestRegistry::class, 'resolveEntries');
                $entries = $reflection->invoke($this, $controller);

                $prop = new \ReflectionProperty(RestRegistry::class, 'entries');
                $existing = $prop->getValue($this);
                $prop->setValue($this, array_merge($existing, $entries));
            }
        };
    }

    #[Test]
    public function resolvesControllerWithSingleRoute(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(1, $entries);
        self::assertSame('test/v1', $entries[0]->namespace);
        self::assertSame('/items', $entries[0]->route);
        self::assertSame(['GET'], $entries[0]->methods);
    }

    #[Test]
    public function resolvesControllerWithMultipleRoutes(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }

            #[Route('/(?P<id>\d+)', methods: HttpMethod::GET)]
            public function show(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(2, $entries);
        self::assertSame('/items', $entries[0]->route);
        self::assertSame('/items/(?P<id>\d+)', $entries[1]->route);
    }

    #[Test]
    public function resolvesClassLevelPermission(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(capability: 'edit_posts')] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('edit_posts', $entries[0]->permission->capability);
    }

    #[Test]
    public function methodLevelPermissionOverridesClassLevel(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }

            #[Route(methods: HttpMethod::POST)]
            #[Permission(capability: 'edit_posts')]
            public function create(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertTrue($entries[0]->permission->public);
        self::assertSame('edit_posts', $entries[1]->permission->capability);
    }

    #[Test]
    public function combinesClassRouteWithMethodRoute(): void
    {
        $controller = new #[Route('/products', namespace: 'shop/v1')] #[Permission(public: true)] class {
            #[Route('/(?P<id>\d+)/reviews', methods: HttpMethod::GET)]
            public function reviews(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('/products/(?P<id>\d+)/reviews', $entries[0]->route);
        self::assertSame('shop/v1', $entries[0]->namespace);
    }

    #[Test]
    public function resolvesParameterLevelParamAttributes(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(
                #[Param(minimum: 1, maximum: 100)]
                int $perPage = 10,
            ): array {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
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
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
            public function create(string $title, bool $published = false): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
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
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
            public function create(string $title, \WpPack\Component\HttpFoundation\Request $request): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('title', $entries[0]->params[0]->name);
    }

    #[Test]
    public function throwsWhenNoClassLevelRoute(): void
    {
        $controller = new class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a #[Route] attribute');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenClassRouteHasNoNamespace(): void
    {
        $controller = new #[Route('/items')] class {
            #[Route(methods: HttpMethod::GET)]
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
        $controller = new #[Route('/items', namespace: 'test/v1')] class {
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no methods with #[Route] attributes');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function repeatableRouteCreatesMultipleEntries(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::PUT)]
            #[Route(methods: HttpMethod::PATCH)]
            public function update(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(2, $entries);
        self::assertSame(['PUT'], $entries[0]->methods);
        self::assertSame(['PATCH'], $entries[1]->methods);
    }

    #[Test]
    public function registerAddsRestApiInitHook(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_action('rest_api_init'));
    }

    #[Test]
    public function getRegisteredEntriesReturnsEmptyByDefault(): void
    {
        $registry = new RestRegistry();

        self::assertSame([], $registry->getRegisteredEntries());
    }

    #[Test]
    public function resolvesFloatParameterAsNumberType(): void
    {
        $controller = new #[Route('/metrics', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
            public function store(float $score): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('number', $entries[0]->params[0]->type);
    }

    #[Test]
    public function resolvesArrayParameterAsArrayType(): void
    {
        $controller = new #[Route('/batch', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
            public function process(array $ids): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('array', $entries[0]->params[0]->type);
    }

    #[Test]
    public function skipWpRestRequestParameter(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(\WP_REST_Request $request, int $page = 1): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('page', $entries[0]->params[0]->name);
    }

    #[Test]
    public function convertsCamelCaseToSnakeCase(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(int $perPage = 10, string $sortOrder = 'asc'): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('per_page', $entries[0]->params[0]->name);
        self::assertSame('sort_order', $entries[0]->params[1]->name);
    }

    #[Test]
    public function permissionCallbackIsReturnTrueForPublic(): void
    {
        if (!function_exists('register_rest_route')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/public', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function index(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/public'][0];
        self::assertSame('__return_true', $route['permission_callback']);
    }

    #[Test]
    public function permissionCallbackChecksCapability(): void
    {
        if (!function_exists('register_rest_route')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/admin', namespace: 'test/v1')] #[Permission(capability: 'manage_options')] class {
            #[Route(methods: HttpMethod::GET)]
            public function index(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/admin'][0];
        self::assertIsCallable($route['permission_callback']);
    }

    #[Test]
    public function callbackExtractsParamsFromRequest(): void
    {
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $capturedId = null;
        $capturedTitle = null;
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?int $capturedId = null;
            public ?string $capturedTitle = null;

            #[Route('/(?P<id>\d+)', methods: HttpMethod::PUT)]
            public function update(int $id, string $title): array
            {
                $this->capturedId = $id;
                $this->capturedTitle = $title;

                return ['id' => $id, 'title' => $title];
            }
        };

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

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
        if (!class_exists(\WP_REST_Request::class)) {
            self::markTestSkipped('WordPress classes are not available.');
        }

        $controller = new #[Route('/inject-request', namespace: 'test/v1')] #[Permission(public: true)] class {
            public ?\WpPack\Component\HttpFoundation\Request $capturedRequest = null;

            #[Route(methods: HttpMethod::GET)]
            public function index(\WpPack\Component\HttpFoundation\Request $request): array
            {
                $this->capturedRequest = $request;

                return [];
            }
        };

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

        $wpRequest = new \WP_REST_Request('GET', '/test/v1/inject-request');
        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/inject-request'][0];
        call_user_func($route['callback'], $wpRequest);

        self::assertInstanceOf(Request::class, $controller->capturedRequest);
    }

    #[Test]
    public function createArgsIncludesValidateCallback(): void
    {
        if (!function_exists('register_rest_route')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/validate-items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
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

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/validate-items'][0];
        self::assertArrayHasKey('title', $route['args']);
        self::assertArrayHasKey('validate_callback', $route['args']['title']);
        self::assertIsCallable($route['args']['title']['validate_callback']);
    }

    #[Test]
    public function createArgsIncludesSanitizeCallback(): void
    {
        if (!function_exists('register_rest_route')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/sanitize-items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
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

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

        $routes = rest_get_server()->get_routes();
        $route = $routes['/test/v1/sanitize-items'][0];
        self::assertArrayHasKey('title', $route['args']);
        self::assertArrayHasKey('sanitize_callback', $route['args']['title']);
        self::assertIsCallable($route['args']['title']['sanitize_callback']);
    }

    #[Test]
    public function permissionCallbackInvokesControllerMethod(): void
    {
        if (!function_exists('register_rest_route')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/items', namespace: 'test/v1')] class {
            #[Route(methods: HttpMethod::GET)]
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

        $registry = new RestRegistry();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $entries[0]->register();

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
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function index(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('/items', $entries[0]->route);
    }
}
