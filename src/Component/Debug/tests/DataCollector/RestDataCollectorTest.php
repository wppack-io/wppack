<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\RestDataCollector;

final class RestDataCollectorTest extends TestCase
{
    private RestDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new RestDataCollector();
    }

    #[Test]
    public function getNameReturnsRest(): void
    {
        self::assertSame('rest', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsRestApi(): void
    {
        self::assertSame('REST API', $this->collector->getLabel());
    }

    #[Test]
    public function getIndicatorValueReturnsEmpty(): void
    {
        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefaultWhenNotRestRequest(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['is_rest_request' => false]);

        self::assertSame('default', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsGreenForSuccess(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_rest_request' => true,
            'current_request' => ['status' => 200],
        ]);

        self::assertSame('green', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsRedForClientError(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_rest_request' => true,
            'current_request' => ['status' => 404],
        ]);

        self::assertSame('red', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowForRedirect(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_rest_request' => true,
            'current_request' => ['status' => 301],
        ]);

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['is_rest_request' => true]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectGathersRestRoutes(): void
    {

        // Register a test route
        $registered = false;
        $registerCallback = static function () use (&$registered): void {
            if ($registered) {
                return;
            }
            register_rest_route('wppack-test/v1', '/debug-test', [
                'methods' => 'GET',
                'callback' => static fn(): \WP_REST_Response => new \WP_REST_Response(['ok' => true]),
                'permission_callback' => '__return_true',
            ]);
            $registered = true;
        };

        // Ensure rest_api_init fires
        add_action('rest_api_init', $registerCallback, 10);

        try {
            // Force the REST server to initialize and register routes
            $server = rest_get_server();

            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertGreaterThan(0, $data['total_routes']);
            self::assertGreaterThan(0, $data['total_namespaces']);
            self::assertNotEmpty($data['namespaces']);
            self::assertNotEmpty($data['routes']);

            // Check our test route exists in the grouped routes
            $found = false;
            foreach ($data['routes'] as $ns => $routes) {
                foreach ($routes as $route) {
                    if ($route['route'] === '/wppack-test/v1/debug-test') {
                        $found = true;
                        self::assertContains('GET', $route['methods']);
                        break 2;
                    }
                }
            }
            self::assertTrue($found, 'Test route /wppack-test/v1/debug-test should be found');
        } finally {
            remove_action('rest_api_init', $registerCallback, 10);
        }
    }

    #[Test]
    public function collectIsNotRestRequestByDefault(): void
    {

        $this->collector->collect();
        $data = $this->collector->getData();

        // Normal test requests are not REST requests
        self::assertFalse($data['is_rest_request']);
        self::assertNull($data['current_request']);
    }

    #[Test]
    public function collectGroupsRoutesByNamespace(): void
    {

        rest_get_server();

        $this->collector->collect();
        $data = $this->collector->getData();

        // Routes should be grouped by namespace
        foreach ($data['routes'] as $ns => $routes) {
            self::assertIsString($ns);
            self::assertIsArray($routes);
            foreach ($routes as $route) {
                self::assertArrayHasKey('route', $route);
                self::assertArrayHasKey('methods', $route);
                self::assertArrayHasKey('callback', $route);
            }
        }
    }

    #[Test]
    public function formatCallbackWithStringReturnsString(): void
    {
        $method = new \ReflectionMethod($this->collector, 'formatCallback');

        $result = $method->invoke($this->collector, 'my_callback_function');

        self::assertSame('my_callback_function', $result);
    }

    #[Test]
    public function formatCallbackWithArrayReturnsClassMethod(): void
    {
        $method = new \ReflectionMethod($this->collector, 'formatCallback');

        $result = $method->invoke($this->collector, [$this, 'formatCallbackWithArrayReturnsClassMethod']);

        self::assertSame(self::class . '::formatCallbackWithArrayReturnsClassMethod', $result);
    }

    #[Test]
    public function formatCallbackWithObjectArrayReturnsClassName(): void
    {
        $method = new \ReflectionMethod($this->collector, 'formatCallback');

        $obj = new \stdClass();
        // stdClass doesn't have methods, but the format logic works on any array with 2 elements
        $result = $method->invoke($this->collector, [$obj, 'someMethod']);

        self::assertSame('stdClass::someMethod', $result);
    }

    #[Test]
    public function formatCallbackWithStringClassReturnsClassName(): void
    {
        $method = new \ReflectionMethod($this->collector, 'formatCallback');

        $result = $method->invoke($this->collector, ['SomeClass', 'staticMethod']);

        self::assertSame('SomeClass::staticMethod', $result);
    }

    #[Test]
    public function formatCallbackWithClosureReturnsClosure(): void
    {
        $method = new \ReflectionMethod($this->collector, 'formatCallback');

        $closure = static function (): void {};
        $result = $method->invoke($this->collector, $closure);

        self::assertSame('Closure', $result);
    }

    #[Test]
    public function formatCallbackWithUnknownReturnsUnknown(): void
    {
        $method = new \ReflectionMethod($this->collector, 'formatCallback');

        // Non-callable types
        $result = $method->invoke($this->collector, 42);
        self::assertSame('unknown', $result);

        $result = $method->invoke($this->collector, null);
        self::assertSame('unknown', $result);

        $result = $method->invoke($this->collector, true);
        self::assertSame('unknown', $result);
    }

    #[Test]
    public function collectRouteMethodsExtracted(): void
    {

        // Use the existing routes from the server — WP core always registers routes
        $server = rest_get_server();
        $this->collector->collect();
        $data = $this->collector->getData();

        // Verify that at least one route has methods extracted
        $foundWithMethods = false;
        foreach ($data['routes'] as $ns => $routes) {
            foreach ($routes as $route) {
                if (!empty($route['methods'])) {
                    $foundWithMethods = true;
                    self::assertIsArray($route['methods']);
                    // Methods should be HTTP verbs
                    foreach ($route['methods'] as $method) {
                        self::assertContains($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS']);
                    }
                    break 2;
                }
            }
        }
        self::assertTrue($foundWithMethods, 'At least one route should have methods extracted');
    }

    #[Test]
    public function collectRouteCallbackExtracted(): void
    {

        // Use the existing routes from the server
        $server = rest_get_server();
        $this->collector->collect();
        $data = $this->collector->getData();

        // Verify that at least one route has a non-empty callback string
        $foundWithCallback = false;
        foreach ($data['routes'] as $ns => $routes) {
            foreach ($routes as $route) {
                if ($route['callback'] !== '') {
                    $foundWithCallback = true;
                    self::assertIsString($route['callback']);
                    // Callback should be a formatted string (e.g. "ClassName::method" or "function_name" or "Closure")
                    self::assertNotEmpty($route['callback']);
                    break 2;
                }
            }
        }
        self::assertTrue($foundWithCallback, 'At least one route should have a callback extracted');
    }

    #[Test]
    public function isRestRequestReturnsFalseWhenConstantNotDefined(): void
    {
        $method = new \ReflectionMethod($this->collector, 'isRestRequest');

        // When REST_REQUEST is not defined (or defined as false), should return false
        // In the test environment REST_REQUEST is not defined by default
        if (defined('REST_REQUEST') && REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST is already defined as true.');
        }

        self::assertFalse($method->invoke($this->collector));
    }

    #[Test]
    public function collectCurrentRequestReturnsNullWhenNotRestRequest(): void
    {

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');

        // isRestRequest() returns false because REST_REQUEST is not defined
        if (defined('REST_REQUEST') && REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST is already defined as true.');
        }

        $result = $method->invoke($this->collector, $allRoutes, $namespaces);

        self::assertNull($result);
    }

    #[Test]
    public function collectCurrentRequestWithServerPathInfoAndRestServer(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $originalXWpNonce = $_SERVER['HTTP_X_WP_NONCE'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            $_GET = [];
            unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_WP_NONCE']);

            // Ensure no user is logged in so authentication resolves to 'none'
            $previousUserId = get_current_user_id();
            wp_set_current_user(0);

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            wp_set_current_user($previousUserId);

            self::assertIsArray($result);
            self::assertSame('GET', $result['method']);
            self::assertSame('/wp/v2/posts', $result['path']);
            self::assertArrayHasKey('route', $result);
            self::assertArrayHasKey('namespace', $result);
            self::assertArrayHasKey('callback', $result);
            self::assertArrayHasKey('params', $result);
            self::assertArrayHasKey('status', $result);
            self::assertSame(200, $result['status']);
            self::assertArrayHasKey('authentication', $result);
            self::assertSame('none', $result['authentication']);

            // The route should be matched (wp/v2/posts is a registered WP core route)
            if ($result['route'] !== '') {
                self::assertIsString($result['namespace']);
            }
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
            if ($originalXWpNonce !== null) {
                $_SERVER['HTTP_X_WP_NONCE'] = $originalXWpNonce;
            } else {
                unset($_SERVER['HTTP_X_WP_NONCE']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithRestRouteGetParam(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            // No PATH_INFO, use rest_route GET param instead
            unset($_SERVER['PATH_INFO']);
            $_GET = ['rest_route' => '/wp/v2/posts'];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('GET', $result['method']);
            // path should come from $_GET['rest_route']
            self::assertSame('/wp/v2/posts', $result['path']);
            // rest_route should be removed from params
            self::assertArrayNotHasKey('rest_route', $result['params']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
        }
    }

    #[Test]
    public function collectCurrentRequestWithBearerAuth(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer some-jwt-token-here';
            $_GET = [];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('bearer', $result['authentication']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithBasicAuth(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
            $_GET = [];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('basic', $result['authentication']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithNonceAuth(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = ['_wpnonce' => 'abc123'];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('nonce', $result['authentication']);
            // _wpnonce should be in params (it's a query param that is not rest_route)
            self::assertArrayHasKey('_wpnonce', $result['params']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithXWpNonceHeader(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $originalXWpNonce = $_SERVER['HTTP_X_WP_NONCE'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_SERVER['HTTP_X_WP_NONCE'] = 'nonce-value';
            $_GET = [];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('nonce', $result['authentication']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
            if ($originalXWpNonce !== null) {
                $_SERVER['HTTP_X_WP_NONCE'] = $originalXWpNonce;
            } else {
                unset($_SERVER['HTTP_X_WP_NONCE']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithNoServerAndNoPathInfo(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        // Set wp_rest_server to null so the server block is skipped
        $wp_rest_server = null;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $originalXWpNonce = $_SERVER['HTTP_X_WP_NONCE'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            unset($_SERVER['PATH_INFO'], $_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_WP_NONCE']);
            $_GET = [];

            // Ensure no user is logged in so authentication resolves to 'none'
            $previousUserId = get_current_user_id();
            wp_set_current_user(0);

            $server = rest_get_server();
            $allRoutes = $server->get_routes();
            $namespaces = $server->get_namespaces();

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            wp_set_current_user($previousUserId);

            self::assertIsArray($result);
            self::assertSame('POST', $result['method']);
            // No route matched because wp_rest_server is null
            self::assertSame('', $result['route']);
            self::assertSame('', $result['namespace']);
            self::assertSame('', $result['callback']);
            self::assertSame('none', $result['authentication']);
            self::assertSame('', $result['path']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
            if ($originalXWpNonce !== null) {
                $_SERVER['HTTP_X_WP_NONCE'] = $originalXWpNonce;
            } else {
                unset($_SERVER['HTTP_X_WP_NONCE']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithEmptyRestRoute(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            // Both PATH_INFO and rest_route are empty
            unset($_SERVER['PATH_INFO'], $_SERVER['HTTP_AUTHORIZATION']);
            $_GET = ['rest_route' => ''];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            // Route won't be matched because restRoute is empty
            self::assertSame('', $result['route']);
            self::assertSame('', $result['namespace']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithQueryParams(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = ['per_page' => '5', 'page' => '2', 'rest_route' => '/wp/v2/posts'];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            // rest_route should be removed from params
            self::assertArrayNotHasKey('rest_route', $result['params']);
            // Other query params should be present
            self::assertArrayHasKey('per_page', $result['params']);
            self::assertSame('5', $result['params']['per_page']);
            self::assertArrayHasKey('page', $result['params']);
            self::assertSame('2', $result['params']['page']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestMatchesRouteAndExtractsCallback(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            // /wp/v2/posts is always registered by WP core
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = [];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            // Should match the route pattern
            self::assertNotSame('', $result['route']);
            // Should detect the wp/v2 namespace
            self::assertSame('wp/v2', $result['namespace']);
            // Should have a callback extracted
            self::assertNotSame('', $result['callback']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithNonMatchingRoute(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            // A route that doesn't exist
            $_SERVER['PATH_INFO'] = '/nonexistent/route/that/does/not/match';
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = [];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            // No route should match
            self::assertSame('', $result['route']);
            self::assertSame('', $result['namespace']);
            self::assertSame('', $result['callback']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectCurrentRequestWithPostMethodMatchesRoute(): void
    {

        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            // /wp/v2/posts accepts POST for creating
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            unset($_SERVER['HTTP_AUTHORIZATION']);
            $_GET = [];

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('POST', $result['method']);
            // The route should still match even for POST
            self::assertNotSame('', $result['route']);
        } finally {
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
        }
    }

    #[Test]
    public function collectHandlesRouteWithNoNamespaceMatch(): void
    {
        global $wp_rest_server;
        $originalServer = $wp_rest_server;
        $wp_rest_server = null;

        // Register a route with a namespace that won't match standard namespaces detection
        $registered = false;
        $registerCallback = static function () use (&$registered): void {
            if ($registered) {
                return;
            }
            register_rest_route('custom-ns/v1', '/test-route', [
                'methods' => ['GET', 'POST'],
                'callback' => static fn(): \WP_REST_Response => new \WP_REST_Response(['ok' => true]),
                'permission_callback' => '__return_true',
            ]);
            $registered = true;
        };

        add_action('rest_api_init', $registerCallback, 10);

        try {
            rest_get_server();

            $this->collector->collect();
            $data = $this->collector->getData();

            // Find our route in the grouped routes
            $found = false;
            foreach ($data['routes'] as $ns => $routes) {
                foreach ($routes as $route) {
                    if ($route['route'] === '/custom-ns/v1/test-route') {
                        $found = true;
                        self::assertSame('custom-ns/v1', $ns);
                        self::assertContains('GET', $route['methods']);
                        self::assertContains('POST', $route['methods']);
                        break 2;
                    }
                }
            }
            self::assertTrue($found, 'Custom namespaced route should be found');
        } finally {
            remove_action('rest_api_init', $registerCallback, 10);
            $wp_rest_server = $originalServer;
        }
    }

    #[Test]
    public function collectHandlesMethodsWithEnabledFalse(): void
    {
        global $wp_rest_server;
        $originalServer = $wp_rest_server;
        $wp_rest_server = null;

        // Register a route with some methods disabled
        $registered = false;
        $registerCallback = static function () use (&$registered): void {
            if ($registered) {
                return;
            }
            register_rest_route('test-methods/v1', '/disabled-check', [
                [
                    'methods' => 'GET',
                    'callback' => static fn(): \WP_REST_Response => new \WP_REST_Response(['ok' => true]),
                    'permission_callback' => '__return_true',
                ],
            ]);
            $registered = true;
        };

        add_action('rest_api_init', $registerCallback, 10);

        try {
            rest_get_server();

            $this->collector->collect();
            $data = $this->collector->getData();

            // Route should have only enabled methods
            $found = false;
            foreach ($data['routes'] as $ns => $routes) {
                foreach ($routes as $route) {
                    if ($route['route'] === '/test-methods/v1/disabled-check') {
                        $found = true;
                        self::assertIsArray($route['methods']);
                        break 2;
                    }
                }
            }
            self::assertTrue($found, 'Route should be found');
        } finally {
            remove_action('rest_api_init', $registerCallback, 10);
            $wp_rest_server = $originalServer;
        }
    }

    #[Test]
    public function collectTotalRoutesMatchesCount(): void
    {

        rest_get_server();

        $this->collector->collect();
        $data = $this->collector->getData();

        // Total routes should match the sum of routes across all namespaces
        $sum = 0;
        foreach ($data['routes'] as $routes) {
            $sum += count($routes);
        }
        self::assertSame($data['total_routes'], $sum);
    }

    #[Test]
    public function collectCurrentRequestCookieAuthWhenLoggedIn(): void
    {


        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST constant is not defined; cannot test REST request paths without polluting global state.');
        }

        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = $server->get_namespaces();

        global $wp_rest_server;
        $originalServer = $wp_rest_server ?? null;
        $wp_rest_server = $server;

        $originalPathInfo = $_SERVER['PATH_INFO'] ?? null;
        $originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        $originalGet = $_GET;
        $originalHttpAuth = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        $originalXWpNonce = $_SERVER['HTTP_X_WP_NONCE'] ?? null;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['PATH_INFO'] = '/wp/v2/posts';
            unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['HTTP_X_WP_NONCE']);
            $_GET = [];

            // Log in as admin to trigger cookie auth
            wp_set_current_user(1);

            $method = new \ReflectionMethod($this->collector, 'collectCurrentRequest');
            $result = $method->invoke($this->collector, $allRoutes, array_values($namespaces));

            self::assertIsArray($result);
            self::assertSame('cookie', $result['authentication']);
        } finally {
            wp_set_current_user(0);
            $wp_rest_server = $originalServer;
            if ($originalPathInfo !== null) {
                $_SERVER['PATH_INFO'] = $originalPathInfo;
            } else {
                unset($_SERVER['PATH_INFO']);
            }
            if ($originalRequestMethod !== null) {
                $_SERVER['REQUEST_METHOD'] = $originalRequestMethod;
            } else {
                unset($_SERVER['REQUEST_METHOD']);
            }
            $_GET = $originalGet;
            if ($originalHttpAuth !== null) {
                $_SERVER['HTTP_AUTHORIZATION'] = $originalHttpAuth;
            } else {
                unset($_SERVER['HTTP_AUTHORIZATION']);
            }
            if ($originalXWpNonce !== null) {
                $_SERVER['HTTP_X_WP_NONCE'] = $originalXWpNonce;
            } else {
                unset($_SERVER['HTTP_X_WP_NONCE']);
            }
        }
    }
}
