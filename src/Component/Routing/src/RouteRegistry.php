<?php

declare(strict_types=1);

namespace WpPack\Component\Routing;

use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\Route;

final class RouteRegistry
{
    /** @var array<string, RouteEntry> */
    private array $routes = [];

    public function register(object $controller): void
    {
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
     * @return array<string, RouteEntry>
     */
    public function getRegisteredRoutes(): array
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

        $classRoutes = $reflection->getAttributes(Route::class);
        if ($classRoutes !== []) {
            if (!$reflection->hasMethod('__invoke')) {
                throw new \LogicException(sprintf(
                    'Class "%s" has #[Route] attribute but does not implement __invoke().',
                    $controller::class,
                ));
            }

            $route = $classRoutes[0]->newInstance();
            $entries[] = new RouteEntry(
                $route->name,
                $route->regex,
                $route->query,
                $route->position,
                $classTags,
                $controller->__invoke(...),
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
            $methodTags = $this->resolveRewriteTags($method);
            $entries[] = new RouteEntry(
                $route->name,
                $route->regex,
                $route->query,
                $route->position,
                array_merge($classTags, $methodTags),
                $controller->{$method->getName()}(...),
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
}
