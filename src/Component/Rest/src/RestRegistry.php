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

namespace WpPack\Component\Rest;

use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\Exception\RouteNotFoundException;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Authorization\IsGrantedChecker;
use WpPack\Component\Security\Security;

final class RestRegistry
{
    /** @var list<RestEntry> */
    private array $entries = [];

    /** @var array<string, RestEntry> */
    private array $namedEntries = [];

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
            if ($entry->name !== '') {
                $this->namedEntries[$entry->name] = $entry;
            }
            add_action('rest_api_init', $entry->register(...));
        }
    }

    /**
     * @return list<RestEntry>
     */
    public function all(): array
    {
        return $this->entries;
    }

    public function has(string $name): bool
    {
        return isset($this->namedEntries[$name]);
    }

    /**
     * @throws RouteNotFoundException
     */
    public function get(string $name): RestEntry
    {
        if (!isset($this->namedEntries[$name])) {
            throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
        }

        return $this->namedEntries[$name];
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

        // __invoke support: class-level #[RestRoute] with methods → use __invoke as handler
        if ($classRoute->methods !== []) {
            if (!$reflection->hasMethod('__invoke')) {
                throw new \LogicException(sprintf(
                    'Class "%s" has #[RestRoute] with methods but does not implement __invoke().',
                    $controller::class,
                ));
            }

            $method = $reflection->getMethod('__invoke');
            $methodPermissionAttrs = $method->getAttributes(Permission::class);
            $methodPermission = $methodPermissionAttrs !== [] ? $methodPermissionAttrs[0]->newInstance() : $classPermission;

            $methodIsGrantedAttrs = array_map(
                static fn(\ReflectionAttribute $a) => $a->newInstance(),
                $method->getAttributes(IsGranted::class),
            );
            $isGrantedAttributes = array_merge($classIsGrantedAttrs, $methodIsGrantedAttrs);

            $params = $this->resolveParams($method);
            $handler = $this->createHandler($controller, $method, $params);

            $fullPath = $classRoute->route;
            $fullRoute = self::compilePath($fullPath, $classRoute->requirements);
            if ($fullRoute === '') {
                $fullRoute = '/';
            }

            $entries[] = new RestEntry(
                $classRoute->namespace,
                $fullRoute,
                $classRoute->methods,
                $methodPermission,
                $params,
                $handler,
                $controller,
                $isGrantedAttributes,
                $classRoute->name,
                $fullPath,
            );
        }

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__invoke') {
                continue;
            }

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
                $fullPath = rtrim($classRoute->route, '/') . $methodRoute->route;
                $mergedRequirements = array_merge($classRoute->requirements, $methodRoute->requirements);
                $fullRoute = self::compilePath($fullPath, $mergedRequirements);
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
                    $methodRoute->name,
                    $fullPath,
                );
            }
        }

        if ($entries === []) {
            throw new \LogicException(sprintf(
                'Class "%s" has no methods with #[RestRoute] attributes and no __invoke() with methods.',
                $controller::class,
            ));
        }

        return $entries;
    }

    /**
     * Compiles a path pattern, converting {param} placeholders to regex groups.
     *
     * @param array<string, string> $requirements
     */
    private static function compilePath(string $path, array $requirements = []): string
    {
        $params = RestEntry::extractParams($path);
        if ($params === []) {
            return $path;
        }

        $compiled = $path;
        foreach ($params as $param) {
            $pattern = $requirements[$param] ?? '[^/]+';
            $compiled = str_replace('{' . $param . '}', '(?P<' . $param . '>' . $pattern . ')', $compiled);
        }

        return $compiled;
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
