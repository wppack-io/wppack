<?php

declare(strict_types=1);

namespace WpPack\Component\Routing;

use WpPack\Component\HttpFoundation\Exception\ForbiddenException;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Exception\RouteNotFoundException;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\Authorization\IsGrantedChecker;
use WpPack\Component\Role\Exception\AccessDeniedException;
use WpPack\Component\Security\Security;
use WpPack\Component\Templating\TemplateRendererInterface;

final class RouteRegistry
{
    /** @var array<string, RouteEntry> */
    private array $routes = [];

    public function __construct(
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?IsGrantedChecker $isGrantedChecker = null,
    ) {}

    public function register(object $controller): void
    {
        if ($controller instanceof AbstractController) {
            if ($this->security !== null) {
                $controller->setSecurity($this->security);
            }
            if ($this->renderer !== null) {
                $controller->setTemplateRenderer($this->renderer);
            }
        }

        foreach ($this->resolveRoutes($controller) as $entry) {
            $this->routes[$entry->name] = $entry;

            add_action('init', $entry->registerRoute(...));
            add_filter('query_vars', $entry->filterQueryVars(...));
            add_action('template_redirect', $entry->handleTemplateRedirect(...));
            add_filter('template_include', $entry->filterTemplateInclude(...));
        }
    }

    public function has(string $name): bool
    {
        return isset($this->routes[$name]);
    }

    /**
     * @throws RouteNotFoundException
     */
    public function get(string $name): RouteEntry
    {
        if (!isset($this->routes[$name])) {
            throw new RouteNotFoundException(sprintf('Route "%s" does not exist.', $name));
        }

        return $this->routes[$name];
    }

    /**
     * @return array<string, RouteEntry>
     */
    public function all(): array
    {
        return $this->routes;
    }

    public function flush(): void
    {
        flush_rewrite_rules();
    }

    /**
     * @return list<RouteEntry>
     */
    private function resolveRoutes(object $controller): array
    {
        $entries = [];
        $reflection = new \ReflectionClass($controller);
        $classTags = $this->resolveRewriteTags($reflection);
        $checker = $this->isGrantedChecker ?? new IsGrantedChecker($this->security);

        $classRoutes = $reflection->getAttributes(Route::class);
        if ($classRoutes !== []) {
            if (!$reflection->hasMethod('__invoke')) {
                throw new \LogicException(sprintf(
                    'Class "%s" has #[Route] attribute but does not implement __invoke().',
                    $controller::class,
                ));
            }

            $route = $classRoutes[0]->newInstance();
            $regex = RouteEntry::compilePath($route->path, $route->requirements);
            $query = RouteEntry::buildQueryFromPath($route->path, $route->vars);
            $queryVarNames = RouteEntry::parseQueryVars($query);
            $method = $reflection->getMethod('__invoke');
            $entries[] = new RouteEntry(
                $route->name,
                $regex,
                $query,
                $route->position,
                $classTags,
                $this->createHandler($controller, $method, $queryVarNames, IsGrantedChecker::resolve($reflection, $method), $checker),
                $route->path,
            );
        }

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getName() === '__invoke') {
                continue;
            }

            $methodRoutes = $method->getAttributes(Route::class);
            if ($methodRoutes === []) {
                continue;
            }

            $route = $methodRoutes[0]->newInstance();
            $regex = RouteEntry::compilePath($route->path, $route->requirements);
            $query = RouteEntry::buildQueryFromPath($route->path, $route->vars);
            $queryVarNames = RouteEntry::parseQueryVars($query);
            $methodTags = $this->resolveRewriteTags($method);
            $entries[] = new RouteEntry(
                $route->name,
                $regex,
                $query,
                $route->position,
                array_merge($classTags, $methodTags),
                $this->createHandler($controller, $method, $queryVarNames, IsGrantedChecker::resolve($reflection, $method), $checker),
                $route->path,
            );
        }

        if ($entries === []) {
            throw new \LogicException(sprintf(
                'Class "%s" has no #[Route] attributes on the class or its methods.',
                $controller::class,
            ));
        }

        return $entries;
    }

    /**
     * @param list<string> $queryVarNames
     * @param list<IsGranted> $grants
     */
    private function createHandler(
        object $controller,
        \ReflectionMethod $method,
        array $queryVarNames,
        array $grants,
        IsGrantedChecker $checker,
    ): \Closure {
        $methodName = $method->getName();
        $requestParamIndex = null;
        /** @var list<array{index: int}> */
        $injectableParams = [];
        /** @var list<array{index: int, name: string}> */
        $routeParams = [];

        foreach ($method->getParameters() as $index => $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                $requestParamIndex = $index;
                continue;
            }

            if ($parameter->getAttributes(CurrentUser::class) !== []) {
                $injectableParams[] = ['index' => $index];
                continue;
            }

            $routeParams[] = ['index' => $index, 'name' => self::toSnakeCase($parameter->getName())];
        }

        $request = $this->request;
        $security = $this->security;

        return function () use ($controller, $methodName, $requestParamIndex, $injectableParams, $routeParams, $queryVarNames, $request, $security, $grants, $checker): mixed {
            if ($grants !== []) {
                try {
                    $checker->check($grants);
                } catch (AccessDeniedException $e) {
                    throw new ForbiddenException($e->getMessage());
                }
            }
            if ($request !== null) {
                foreach ($queryVarNames as $varName) {
                    $request->attributes->set($varName, get_query_var($varName));
                }
            }

            // Build injection map (index → value)
            $injections = [];

            if ($requestParamIndex !== null) {
                $injections[$requestParamIndex] = $request;
            }

            foreach ($injectableParams as $injectable) {
                $injections[$injectable['index']] = $security?->getUser();
            }

            // Build full argument array in positional order
            $fullArgs = [];
            $routeIndex = 0;
            for ($i = 0, $total = count($routeParams) + count($injections); $i < $total; $i++) {
                if (array_key_exists($i, $injections)) {
                    $fullArgs[] = $injections[$i];
                } else {
                    $fullArgs[] = $request?->attributes->get($routeParams[$routeIndex]['name']);
                    $routeIndex++;
                }
            }

            return $controller->{$methodName}(...$fullArgs);
        };
    }

    /**
     * @param \ReflectionClass<object>|\ReflectionMethod $target
     * @return list<array{string, string}>
     */
    private function resolveRewriteTags(\ReflectionClass|\ReflectionMethod $target): array
    {
        $tags = [];
        foreach ($target->getAttributes(RewriteTag::class) as $attr) {
            $tag = $attr->newInstance();
            $tags[] = [$tag->tag, $tag->regex];
        }

        return $tags;
    }

    private static function toSnakeCase(string $name): string
    {
        return strtolower((string) preg_replace('/[A-Z]/', '_$0', lcfirst($name)));
    }
}
