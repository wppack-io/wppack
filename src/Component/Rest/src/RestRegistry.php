<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\Route;

final class RestRegistry
{
    /** @var list<RestEntry> */
    private array $entries = [];

    public function register(object $controller): void
    {
        foreach ($this->resolveEntries($controller) as $entry) {
            $this->entries[] = $entry;
            add_action('rest_api_init', $entry->register(...));
        }
    }

    /**
     * @return list<RestEntry>
     */
    public function getRegisteredEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<RestEntry>
     */
    private function resolveEntries(object $controller): array
    {
        $reflection = new \ReflectionClass($controller);

        $classRouteAttrs = $reflection->getAttributes(Route::class);
        if ($classRouteAttrs === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have a #[Route] attribute.',
                $controller::class,
            ));
        }

        $classRoute = $classRouteAttrs[0]->newInstance();
        if ($classRoute->namespace === null) {
            throw new \LogicException(sprintf(
                'Class-level #[Route] on "%s" must specify a namespace.',
                $controller::class,
            ));
        }

        $classPermissionAttrs = $reflection->getAttributes(Permission::class);
        $classPermission = $classPermissionAttrs !== [] ? $classPermissionAttrs[0]->newInstance() : null;

        $entries = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodRouteAttrs = $method->getAttributes(Route::class);
            if ($methodRouteAttrs === []) {
                continue;
            }

            $methodPermissionAttrs = $method->getAttributes(Permission::class);
            $methodPermission = $methodPermissionAttrs !== [] ? $methodPermissionAttrs[0]->newInstance() : $classPermission;

            $params = $this->resolveParams($method);
            $handler = $this->createHandler($controller, $method, $params);

            foreach ($methodRouteAttrs as $routeAttr) {
                $methodRoute = $routeAttr->newInstance();
                $fullRoute = rtrim($classRoute->route, '/') . $methodRoute->route;
                if ($fullRoute === '') {
                    $fullRoute = '/';
                }

                $entries[] = new RestEntry(
                    $classRoute->namespace,
                    $fullRoute,
                    $methodRoute->methods,
                    $methodPermission,
                    $params,
                    $handler,
                    $controller,
                );
            }
        }

        if ($entries === []) {
            throw new \LogicException(sprintf(
                'Class "%s" has no methods with #[Route] attributes.',
                $controller::class,
            ));
        }

        return $entries;
    }

    /**
     * @return list<RestParamEntry>
     */
    private function resolveParams(\ReflectionMethod $method): array
    {
        $params = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                if ($typeName === Request::class || $typeName === \WP_REST_Request::class) {
                    continue;
                }
            }

            $name = self::toSnakeCase($parameter->getName());
            $wpType = self::toWpType($parameter);
            $required = !$parameter->isDefaultValueAvailable();
            $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

            $paramAttrs = $parameter->getAttributes(Param::class);
            $param = $paramAttrs !== [] ? $paramAttrs[0]->newInstance() : null;

            $params[] = new RestParamEntry($name, $wpType, $required, $default, $param);
        }

        return $params;
    }

    /**
     * @param list<RestParamEntry> $params
     */
    private function createHandler(object $controller, \ReflectionMethod $method, array $params): \Closure
    {
        $methodName = $method->getName();
        $requestParamIndex = null;

        foreach ($method->getParameters() as $index => $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                if ($typeName === Request::class) {
                    $requestParamIndex = ['index' => $index, 'type' => 'httpfoundation'];
                } elseif ($typeName === \WP_REST_Request::class) {
                    $requestParamIndex = ['index' => $index, 'type' => 'native'];
                }
            }
        }

        return static function (\WP_REST_Request $wpRequest, mixed ...$paramValues) use ($controller, $methodName, $requestParamIndex): mixed {
            if ($requestParamIndex !== null) {
                $inject = $requestParamIndex['type'] === 'httpfoundation'
                    ? Request::createFromGlobals()
                    : $wpRequest;

                array_splice($paramValues, $requestParamIndex['index'], 0, [$inject]);
            }

            return $controller->{$methodName}(...$paramValues);
        };
    }

    private static function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }

    private static function toWpType(\ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return 'string';
        }

        return match ($type->getName()) {
            'int' => 'integer',
            'string' => 'string',
            'bool' => 'boolean',
            'float' => 'number',
            'array' => 'array',
            default => 'string',
        };
    }
}
