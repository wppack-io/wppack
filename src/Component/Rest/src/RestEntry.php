<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\Exception\HttpException;
use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\HttpFoundation\Response;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Security\Attribute\IsGranted;

/** @internal */
final class RestEntry
{
    /**
     * @param list<string> $methods
     * @param list<RestParamEntry> $params
     * @param list<IsGranted> $isGrantedAttributes
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $route,
        public readonly array $methods,
        public readonly ?Permission $permission,
        public readonly array $params,
        private readonly \Closure $handler,
        private readonly ?object $controller = null,
        public readonly array $isGrantedAttributes = [],
    ) {}

    public function register(): void
    {
        register_rest_route($this->namespace, $this->route, [
            'methods' => $this->methods,
            'callback' => $this->createCallback(),
            'permission_callback' => $this->createPermissionCallback(),
            'args' => $this->createArgs(),
        ]);
    }

    /**
     * @return \Closure(\WP_REST_Request): (\WP_REST_Response|\WP_Error)
     */
    private function createCallback(): \Closure
    {
        $handler = $this->handler;
        $params = $this->params;

        return static function (\WP_REST_Request $wpRequest) use ($handler, $params): \WP_REST_Response|\WP_Error {
            try {
                $args = [];
                foreach ($params as $param) {
                    $args[] = $wpRequest->get_param($param->name);
                }

                $response = $handler($wpRequest, ...$args);

                if ($response === null) {
                    return new \WP_REST_Response(null, 204);
                }

                if ($response instanceof JsonResponse) {
                    $wpResponse = new \WP_REST_Response($response->data, $response->statusCode);
                    foreach ($response->headers as $name => $value) {
                        $wpResponse->header($name, $value);
                    }

                    return $wpResponse;
                }

                if ($response instanceof Response) {
                    $wpResponse = new \WP_REST_Response(null, $response->statusCode);
                    foreach ($response->headers as $name => $value) {
                        $wpResponse->header($name, $value);
                    }

                    return $wpResponse;
                }

                if (is_array($response)) {
                    return rest_ensure_response($response);
                }

                return rest_ensure_response($response);
            } catch (HttpException $e) {
                return new \WP_Error(
                    $e->getErrorCode(),
                    $e->getMessage(),
                    ['status' => $e->getStatusCode()],
                );
            }
        };
    }

    /**
     * @return \Closure(\WP_REST_Request): bool|string
     */
    private function createPermissionCallback(): \Closure|string
    {
        $isGrantedAttributes = $this->isGrantedAttributes;
        $hasPermission = $this->permission !== null;
        $hasIsGranted = $isGrantedAttributes !== [];

        if (!$hasPermission && !$hasIsGranted) {
            return '__return_true';
        }

        if ($hasPermission && $this->permission->public && !$hasIsGranted) {
            return '__return_true';
        }

        if ($hasIsGranted && (!$hasPermission || $this->permission->public)) {
            return static function (\WP_REST_Request $request) use ($isGrantedAttributes): bool {
                foreach ($isGrantedAttributes as $grant) {
                    if ($grant->subject !== null ? !current_user_can($grant->attribute, $grant->subject) : !current_user_can($grant->attribute)) {
                        return false;
                    }
                }

                return true;
            };
        }

        if ($hasPermission && $this->permission->callback !== null && $this->controller !== null) {
            $controller = $this->controller;
            $method = $this->permission->callback;

            if (!$hasIsGranted) {
                return static fn(\WP_REST_Request $request): bool => $controller->{$method}($request);
            }

            return static function (\WP_REST_Request $request) use ($controller, $method, $isGrantedAttributes): bool {
                if (!$controller->{$method}($request)) {
                    return false;
                }
                foreach ($isGrantedAttributes as $grant) {
                    if ($grant->subject !== null ? !current_user_can($grant->attribute, $grant->subject) : !current_user_can($grant->attribute)) {
                        return false;
                    }
                }

                return true;
            };
        }

        return '__return_true';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function createArgs(): array
    {
        $args = [];
        foreach ($this->params as $param) {
            $paramArgs = $param->toArgs();

            if ($param->param !== null && $param->param->validate !== null && $this->controller !== null) {
                $controller = $this->controller;
                $method = $param->param->validate;
                $paramArgs['validate_callback'] = static fn(mixed $value, \WP_REST_Request $request, string $key): bool => $controller->{$method}($value, $request, $key);
            }

            if ($param->param !== null && $param->param->sanitize !== null && $this->controller !== null) {
                $controller = $this->controller;
                $method = $param->param->sanitize;
                $paramArgs['sanitize_callback'] = static fn(mixed $value, \WP_REST_Request $request, string $key): mixed => $controller->{$method}($value, $request, $key);
            }

            $args[$param->name] = $paramArgs;
        }

        return $args;
    }
}
