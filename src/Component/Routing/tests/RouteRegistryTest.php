<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Routing\Attribute\RewriteTag;
use WpPack\Component\Routing\Attribute\Route;
use WpPack\Component\Routing\Response\JsonResponse;
use WpPack\Component\Routing\Response\TemplateResponse;
use WpPack\Component\Routing\RouteEntry;
use WpPack\Component\Routing\RoutePosition;
use WpPack\Component\Routing\RouteRegistry;

final class RouteRegistryTest extends TestCase
{
    #[Test]
    public function resolvesSingleActionController(): void
    {
        $controller = new #[Route(name: 'test_route', regex: '^test/([^/]+)/?$', query: 'index.php?test_slug=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('test_route'));
        self::assertCount(1, $registry->getRegisteredRoutes());
    }

    #[Test]
    public function resolvesMultiActionController(): void
    {
        $controller = new class {
            #[Route(
                name: 'route_list',
                regex: '^items/?$',
                query: 'index.php?item_page=list',
            )]
            public function list(): ?TemplateResponse
            {
                return null;
            }

            #[Route(
                name: 'route_detail',
                regex: '^items/([^/]+)/?$',
                query: 'index.php?item_slug=$matches[1]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('route_list'));
        self::assertTrue($registry->has('route_detail'));
        self::assertCount(2, $registry->getRegisteredRoutes());
    }

    #[Test]
    public function resolvesClassLevelRewriteTags(): void
    {
        $controller = new #[RewriteTag('%test_tag%', '([^/]+)')]
        #[Route(name: 'tagged_route', regex: '^tagged/%test_tag%/?$', query: 'index.php?test_tag=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['tagged_route'];
        self::assertSame([['%test_tag%', '([^/]+)']], $entry->rewriteTags);
    }

    #[Test]
    public function resolvesMethodLevelRewriteTags(): void
    {
        $controller = new class {
            #[RewriteTag('%method_tag%', '(\d+)')]
            #[Route(
                name: 'method_tagged',
                regex: '^method/%method_tag%/?$',
                query: 'index.php?method_tag=$matches[1]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['method_tagged'];
        self::assertSame([['%method_tag%', '(\d+)']], $entry->rewriteTags);
    }

    #[Test]
    public function combinesClassAndMethodRewriteTags(): void
    {
        $controller = new #[RewriteTag('%class_tag%', '([^/]+)')] class {
            #[RewriteTag('%method_tag%', '(\d+)')]
            #[Route(
                name: 'combined_tags',
                regex: '^combined/%class_tag%/%method_tag%/?$',
                query: 'index.php?class_tag=$matches[1]&method_tag=$matches[2]',
            )]
            public function show(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $routes = $registry->getRegisteredRoutes();
        $entry = $routes['combined_tags'];
        self::assertSame(
            [['%class_tag%', '([^/]+)'], ['%method_tag%', '(\d+)']],
            $entry->rewriteTags,
        );
    }

    #[Test]
    public function throwsWhenClassRouteWithoutInvoke(): void
    {
        $controller = new #[Route(name: 'broken_route', regex: '^broken/?$', query: 'index.php?broken=1', )] class {};

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('does not implement __invoke()');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenNoRoutesFound(): void
    {
        $controller = new class {
            public function someMethod(): void {}
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no #[Route] attributes');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function hasReturnsTrueForRegistered(): void
    {
        $controller = new #[Route(name: 'exists_route', regex: '^exists/?$', query: 'index.php?exists_page=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        self::assertTrue($registry->has('exists_route'));
    }

    #[Test]
    public function hasReturnsFalseForUnregistered(): void
    {
        $registry = new RouteRegistry();

        self::assertFalse($registry->has('nonexistent_route'));
    }

    #[Test]
    public function registerAddsInitHook(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route(name: 'wp_test_route', regex: '^wp-test/?$', query: 'index.php?wp_test=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_action('init'));
    }

    #[Test]
    public function registerAddsQueryVarsHook(): void
    {
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route(name: 'wp_vars_route', regex: '^wp-vars/?$', query: 'index.php?wp_var=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_filter('query_vars'));
    }

    #[Test]
    public function registerAddsTemplateRedirectHook(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route(name: 'wp_redirect_route', regex: '^wp-redirect/?$', query: 'index.php?wp_redirect=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_action('template_redirect'));
    }

    #[Test]
    public function registerAddsTemplateIncludeHook(): void
    {
        if (!function_exists('add_filter')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route(name: 'wp_include_route', regex: '^wp-include/?$', query: 'index.php?wp_include=$matches[1]', )] class {
            public function __invoke(): ?TemplateResponse
            {
                return null;
            }
        };

        $registry = new RouteRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_filter('template_include'));
    }

    /**
     * Creates a RouteRegistry that bypasses WordPress hook functions for unit testing.
     *
     * Uses reflection to call resolveRoutes() directly and stores entries,
     * avoiding the need for add_action/add_filter.
     */
    private function createRegistryWithoutWordPress(): RouteRegistry
    {
        if (function_exists('add_action')) {
            return new RouteRegistry();
        }

        return new class extends RouteRegistry {
            public function register(object $controller): void
            {
                $reflection = new \ReflectionMethod(RouteRegistry::class, 'resolveRoutes');
                $entries = $reflection->invoke($this, $controller);

                $routesProp = new \ReflectionProperty(RouteRegistry::class, 'routes');
                $routes = $routesProp->getValue($this);

                foreach ($entries as $entry) {
                    $routes[$entry->name] = $entry;
                }

                $routesProp->setValue($this, $routes);
            }
        };
    }
}
