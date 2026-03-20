<?php

declare(strict_types=1);

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Authorization\IsGrantedChecker;
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

        $classIsGrantedAttrs = array_map(
            static fn(\ReflectionAttribute $a) => $a->newInstance(),
            $reflection->getAttributes(IsGranted::class),
        );

        $entries = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $methodRouteAttrs = $method->getAttributes(RestRoute::class);
            if ($methodRouteAttrs === []) {
                continue;
            }

            $methodPermissionAttrs = $method->getAttributes(Permission::class);
            $methodPermission = $methodPermissionAttrs !== [] ? $methodPermissionAttrs[0]->newInstance() : $classPermission;

            $methodIsGrantedAttrs = array_map(
                static fn(\ReflectionAttribute $a) => $a->newInstance(),
                $method->getAttributes(IsGranted::class),
            );
            $isGrantedAttributes = array_merge($classIsGrantedAttrs, $methodIsGrantedAttrs);

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
                    $isGrantedAttributes,
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
        /** @var list<array{index: int}> */
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
                $injectableParams[] = ['index' => $index];
            }
        }

        $security = $this->security;

        return function (\WP_REST_Request $wpRequest, mixed ...$paramValues) use ($controller, $methodName, $requestParamIndex, $injectableParams, $security): mixed {
            // Build injection map (index → value)
            $injections = [];

            if ($requestParamIndex !== null) {
                $injections[$requestParamIndex['index']] = $requestParamIndex['type'] === 'httpfoundation'
                    ? $this->prepareRequest($wpRequest)
                    : $wpRequest;
            }

            foreach ($injectableParams as $injectable) {
                $injections[$injectable['index']] = $security?->getUser();
            }

            // Build full argument array in positional order
            $fullArgs = [];
            $restIndex = 0;
            for ($i = 0, $total = count($paramValues) + count($injections); $i < $total; $i++) {
                if (array_key_exists($i, $injections)) {
                    $fullArgs[] = $injections[$i];
                } else {
                    $fullArgs[] = $paramValues[$restIndex++];
                }
            }

            return $controller->{$methodName}(...$fullArgs);
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
