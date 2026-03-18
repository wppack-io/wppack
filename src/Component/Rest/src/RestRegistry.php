<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Security;

final class RestRegistry
{
    /** @var list<RestEntry> */
    private array $entries = [];

    public function __construct(
        private readonly Request $request,
        private readonly ?Security $security = null,
    ) {}

    public function register(object $controller): void
    {
        if ($this->security !== null && $controller instanceof AbstractRestController) {
            $controller->setSecurity($this->security);
        }

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

        $classRouteAttrs = $reflection->getAttributes(RestRoute::class);
        if ($classRouteAttrs === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have a #[RestRoute] attribute.',
                $controller::class,
            ));
        }

        $classRoute = $classRouteAttrs[0]->newInstance();
        if ($classRoute->namespace === null) {
            throw new \LogicException(sprintf(
                'Class-level #[RestRoute] on "%s" must specify a namespace.',
                $controller::class,
            ));
        }

        $classPermissionAttrs = $reflection->getAttributes(Permission::class);
        $classPermission = $classPermissionAttrs !== [] ? $classPermissionAttrs[0]->newInstance() : null;

        $entries = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodRouteAttrs = $method->getAttributes(RestRoute::class);
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
                'Class "%s" has no methods with #[RestRoute] attributes.',
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

            if ($parameter->getAttributes(CurrentUser::class) !== []) {
                continue;
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
        /** @var array<int, array{index: int, value: mixed}> */
        $injectableParams = [];

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

            if ($parameter->getAttributes(CurrentUser::class) !== []) {
                $injectableParams[] = ['index' => $index, 'value' => null];
            }
        }

        $security = $this->security;

        return function (\WP_REST_Request $wpRequest, mixed ...$paramValues) use ($controller, $methodName, $requestParamIndex, $injectableParams, $security): mixed {
            if ($requestParamIndex !== null) {
                $inject = $requestParamIndex['type'] === 'httpfoundation'
                    ? $this->prepareRequest($wpRequest)
                    : $wpRequest;

                array_splice($paramValues, $requestParamIndex['index'], 0, [$inject]);
            }

            foreach ($injectableParams as $injectable) {
                $value = $security?->getUser();
                array_splice($paramValues, $injectable['index'], 0, [$value]);
            }

            return $controller->{$methodName}(...$paramValues);
        };
    }

    private function prepareRequest(\WP_REST_Request $wpRequest): Request
    {
        foreach ($wpRequest->get_url_params() as $key => $value) {
            $this->request->attributes->set($key, $value);
        }

        return $this->request;
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
