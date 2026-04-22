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

namespace WPPack\Component\Debug\DataCollector;

use WPPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'rest', priority: 125)]
final class RestDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'rest';
    }

    public function getLabel(): string
    {
        return 'REST API';
    }

    public function collect(): void
    {
        $server = rest_get_server();
        $allRoutes = $server->get_routes();
        $namespaces = array_values($server->get_namespaces());

        $routes = [];
        $totalRoutes = 0;

        foreach ($allRoutes as $route => $handlers) {
            // Determine namespace from route
            $ns = '';
            foreach ($namespaces as $namespace) {
                if (str_starts_with(ltrim($route, '/'), $namespace)) {
                    $ns = $namespace;
                    break;
                }
            }
            if ($ns === '') {
                $ns = 'wp/v2';
            }

            $methods = [];
            $callback = '';
            foreach ($handlers as $handler) {
                if (isset($handler['methods'])) {
                    foreach ($handler['methods'] as $method => $enabled) {
                        if ($enabled) {
                            $methods[] = $method;
                        }
                    }
                }
                if ($callback === '' && isset($handler['callback'])) {
                    $callback = $this->formatCallback($handler['callback']);
                }
            }

            $routes[$ns][] = [
                'route' => $route,
                'methods' => array_unique($methods),
                'callback' => $callback,
            ];
            $totalRoutes++;
        }

        $this->data = [
            'is_rest_request' => $this->isRestRequest(),
            'current_request' => $this->collectCurrentRequest($allRoutes, $namespaces),
            'routes' => $routes,
            'namespaces' => $namespaces,
            'total_routes' => $totalRoutes,
            'total_namespaces' => count($namespaces),
        ];
    }

    public function getIndicatorValue(): string
    {
        return '';
    }

    public function getIndicatorColor(): string
    {
        if (!($this->data['is_rest_request'] ?? false)) {
            return 'default';
        }

        $status = (int) (($this->data['current_request'] ?? [])['status'] ?? 200);

        return match (true) {
            $status >= 200 && $status < 300 => 'green',
            $status >= 400 => 'red',
            default => 'yellow',
        };
    }

    private function isRestRequest(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $allRoutes
     * @param list<string> $namespaces
     * @return array<string, mixed>|null
     */
    private function collectCurrentRequest(array $allRoutes, array $namespaces): ?array
    {
        if (!$this->isRestRequest()) {
            return null;
        }

        global $wp_rest_server;

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $route = '';
        $namespace = '';
        $callback = '';
        $params = [];
        $status = 200;
        $authentication = 'none';

        // Detect matched route from the REST server
        if (isset($wp_rest_server) && is_object($wp_rest_server)) {
            // Get the route from the request path
            $restRoute = '';
            if (isset($_SERVER['PATH_INFO'])) {
                $restRoute = (string) $_SERVER['PATH_INFO'];
            } elseif (isset($_GET['rest_route'])) {
                $restRoute = (string) $_GET['rest_route'];
            }

            if ($restRoute !== '') {
                // Match the route against registered routes
                foreach ($allRoutes as $routePattern => $handlers) {
                    $pattern = '#^' . $routePattern . '$#';
                    if (preg_match($pattern, $restRoute, $matches)) {
                        $route = $routePattern;

                        // Extract named params from regex match
                        foreach ($matches as $key => $value) {
                            if (is_string($key)) {
                                $params[$key] = $value;
                            }
                        }

                        // Find matching callback for the request method
                        foreach ($handlers as $handler) {
                            if (isset($handler['methods'][$method]) && $handler['methods'][$method]) {
                                if (isset($handler['callback'])) {
                                    $callback = $this->formatCallback($handler['callback']);
                                }
                                break;
                            }
                        }
                        break;
                    }
                }
            }

            // Determine namespace from matched route
            foreach ($namespaces as $ns) {
                if (str_starts_with(ltrim($route, '/'), $ns)) {
                    $namespace = $ns;
                    break;
                }
            }
        }

        // Detect authentication method
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = (string) $_SERVER['HTTP_AUTHORIZATION'];
            if (str_starts_with(strtolower($authHeader), 'bearer ')) {
                $authentication = 'bearer';
            } elseif (str_starts_with(strtolower($authHeader), 'basic ')) {
                $authentication = 'basic';
            }
        } elseif (isset($_GET['_wpnonce']) || isset($_SERVER['HTTP_X_WP_NONCE'])) {
            $authentication = 'nonce';
        } elseif (is_user_logged_in()) {
            $authentication = 'cookie';
        }

        // Merge query params
        $queryParams = $_GET;
        unset($queryParams['rest_route']);
        $params = array_merge($params, $queryParams);

        return [
            'method' => $method,
            'route' => $route,
            'path' => $_SERVER['PATH_INFO'] ?? ($_GET['rest_route'] ?? ''),
            'namespace' => $namespace,
            'callback' => $callback,
            'params' => $params,
            'status' => $status,
            'authentication' => $authentication,
        ];
    }

    private function formatCallback(mixed $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            $class = is_object($callback[0]) ? $callback[0]::class : (string) $callback[0];

            return $class . '::' . (string) $callback[1];
        }

        if ($callback instanceof \Closure) {
            return 'Closure';
        }

        return 'unknown';
    }
}
