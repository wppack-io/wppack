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

namespace WPPack\Component\Routing;

use WPPack\Component\HttpFoundation\ArgumentResolver;
use WPPack\Component\HttpFoundation\Exception\ForbiddenException;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Role\Exception\AccessDeniedException;
use WPPack\Component\Routing\Attribute\RewriteTag;
use WPPack\Component\Routing\Attribute\Route;
use WPPack\Component\Routing\Exception\RouteNotFoundException;
use WPPack\Component\Security\Security;
use WPPack\Component\Templating\TemplateRendererInterface;

final class RouteRegistry
{
    /** @var array<string, RouteEntry> */
    private array $routes = [];

    public function __construct(
        private readonly ?Request $request = null,
        private readonly ?Security $security = null,
        private readonly ?TemplateRendererInterface $renderer = null,
        private readonly ?IsGrantedChecker $isGrantedChecker = null,
        private readonly ?ArgumentResolver $argumentResolver = null,
        private readonly ?OptionManager $optionManager = null,
    ) {}

    public function register(object $controller): void
    {
        $this->setupController($controller);

        foreach ($this->resolveRoutes($controller) as $entry) {
            $this->registerEntry($entry);
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

    /**
     * @param list<string> $methods
     * @param array<string, string> $requirements
     * @param array<string, string> $vars
     */
    public function addRoute(
        string $path,
        object $controller,
        string $action = '__invoke',
        string $name = '',
        array $requirements = [],
        array $vars = [],
        array $methods = [],
        RoutePosition $position = RoutePosition::Top,
    ): void {
        $this->setupController($controller);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod($action);
        $checker = $this->isGrantedChecker ?? new IsGrantedChecker($this->security);

        $regex = RouteEntry::compilePath($path, $requirements);
        $query = RouteEntry::buildQueryFromPath($path, $vars);
        $queryVarNames = RouteEntry::parseQueryVars($query);

        $entry = new RouteEntry(
            $name,
            $regex,
            $query,
            $position,
            [],
            $this->createHandler($controller, $method, $queryVarNames, IsGrantedChecker::resolve($reflection, $method), $checker),
            $path,
            $methods,
            $this->request,
        );

        $this->registerEntry($entry);
    }

    public function flush(): void
    {
        flush_rewrite_rules();
    }

    public function invalidate(): void
    {
        if ($this->optionManager === null) {
            throw new \LogicException('OptionManager is not set. Inject it via the constructor.');
        }

        $this->optionManager->delete('rewrite_rules');
    }

    private function setupController(object $controller): void
    {
        if ($controller instanceof AbstractController) {
            if ($this->security !== null) {
                $controller->setSecurity($this->security);
            }
            if ($this->renderer !== null) {
                $controller->setTemplateRenderer($this->renderer);
            }
        }
    }

    private function registerEntry(RouteEntry $entry): void
    {
        $this->routes[$entry->name] = $entry;

        // If init has already fired, register routes immediately
        if (did_action('init')) {
            $entry->registerRoute();
        } else {
            add_action('init', $entry->registerRoute(...));
        }
        add_filter('query_vars', $entry->filterQueryVars(...));
        add_action('template_redirect', $entry->handleTemplateRedirect(...));
        add_filter('template_include', $entry->filterTemplateInclude(...));
        add_filter('redirect_canonical', $entry->filterRedirectCanonical(...));
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
                $route->methods,
                $this->request,
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
                $route->methods,
                $this->request,
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
        $resolver = $this->argumentResolver?->createResolver($controller, $methodName);
        /** @var list<array{index: int, name: string}> */
        $routeParams = [];
        $paramCount = count($method->getParameters());

        foreach ($method->getParameters() as $index => $parameter) {
            if ($this->argumentResolver?->supports($parameter) === true) {
                continue;
            }

            $routeParams[] = ['index' => $index, 'name' => self::toSnakeCase($parameter->getName())];
        }

        $request = $this->request;

        return function () use ($controller, $methodName, $resolver, $routeParams, $paramCount, $queryVarNames, $request, $grants, $checker): mixed {
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

            $diArgs = $resolver !== null ? $resolver() : [];

            // Build full argument array in positional order
            $fullArgs = [];
            $routeIndex = 0;
            for ($i = 0; $i < $paramCount; $i++) {
                if (array_key_exists($i, $diArgs)) {
                    $fullArgs[] = $diArgs[$i];
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
