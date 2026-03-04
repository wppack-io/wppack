<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Attribute\Param;
use WpPack\Component\Rest\Attribute\Permission;
use WpPack\Component\Rest\Attribute\Route;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Rest\Request;
use WpPack\Component\Rest\RestEntry;
use WpPack\Component\Rest\RestRegistry;

final class RestRegistryTest extends TestCase
{
    private function createRegistryWithoutWordPress(): RestRegistry
    {
        if (function_exists('add_action')) {
            return new RestRegistry();
        }

        return new class extends RestRegistry {
            public function register(object $controller): void
            {
                $reflection = new \ReflectionMethod(RestRegistry::class, 'resolveEntries');
                $entries = $reflection->invoke($this, $controller);

                $prop = new \ReflectionProperty(RestRegistry::class, 'entries');
                $existing = $prop->getValue($this);
                $prop->setValue($this, array_merge($existing, $entries));
            }
        };
    }

    #[Test]
    public function resolvesControllerWithSingleRoute(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(1, $entries);
        self::assertSame('test/v1', $entries[0]->namespace);
        self::assertSame('/items', $entries[0]->route);
        self::assertSame(['GET'], $entries[0]->methods);
    }

    #[Test]
    public function resolvesControllerWithMultipleRoutes(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }

            #[Route('/(?P<id>\d+)', methods: HttpMethod::GET)]
            public function show(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(2, $entries);
        self::assertSame('/items', $entries[0]->route);
        self::assertSame('/items/(?P<id>\d+)', $entries[1]->route);
    }

    #[Test]
    public function resolvesClassLevelPermission(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(capability: 'edit_posts')] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('edit_posts', $entries[0]->permission->capability);
    }

    #[Test]
    public function methodLevelPermissionOverridesClassLevel(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }

            #[Route(methods: HttpMethod::POST)]
            #[Permission(capability: 'edit_posts')]
            public function create(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertTrue($entries[0]->permission->public);
        self::assertSame('edit_posts', $entries[1]->permission->capability);
    }

    #[Test]
    public function combinesClassRouteWithMethodRoute(): void
    {
        $controller = new #[Route('/products', namespace: 'shop/v1')] #[Permission(public: true)] class {
            #[Route('/(?P<id>\d+)/reviews', methods: HttpMethod::GET)]
            public function reviews(int $id): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertSame('/products/(?P<id>\d+)/reviews', $entries[0]->route);
        self::assertSame('shop/v1', $entries[0]->namespace);
    }

    #[Test]
    public function resolvesParameterLevelParamAttributes(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(
                #[Param(minimum: 1, maximum: 100)]
                int $perPage = 10,
            ): array {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('per_page', $entries[0]->params[0]->name);
        self::assertSame('integer', $entries[0]->params[0]->type);
        self::assertFalse($entries[0]->params[0]->required);
        self::assertSame(10, $entries[0]->params[0]->default);
        self::assertSame(1, $entries[0]->params[0]->param->minimum);
        self::assertSame(100, $entries[0]->params[0]->param->maximum);
    }

    #[Test]
    public function infersParamNameTypeRequiredFromPhp(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
            public function create(string $title, bool $published = false): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        $params = $entries[0]->params;

        self::assertCount(2, $params);

        self::assertSame('title', $params[0]->name);
        self::assertSame('string', $params[0]->type);
        self::assertTrue($params[0]->required);
        self::assertNull($params[0]->default);

        self::assertSame('published', $params[1]->name);
        self::assertSame('boolean', $params[1]->type);
        self::assertFalse($params[1]->required);
        self::assertFalse($params[1]->default);
    }

    #[Test]
    public function skipsRequestParameter(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::POST)]
            public function create(string $title, Request $request): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(1, $entries[0]->params);
        self::assertSame('title', $entries[0]->params[0]->name);
    }

    #[Test]
    public function throwsWhenNoClassLevelRoute(): void
    {
        $controller = new class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have a #[Route] attribute');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenClassRouteHasNoNamespace(): void
    {
        $controller = new #[Route('/items')] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must specify a namespace');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function throwsWhenNoMethodRoutes(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] class {
            public function list(): array
            {
                return [];
            }
        };

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('has no methods with #[Route] attributes');

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);
    }

    #[Test]
    public function repeatableRouteCreatesMultipleEntries(): void
    {
        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::PUT)]
            #[Route(methods: HttpMethod::PATCH)]
            public function update(): array
            {
                return [];
            }
        };

        $registry = $this->createRegistryWithoutWordPress();
        $registry->register($controller);

        $entries = $registry->getRegisteredEntries();
        self::assertCount(2, $entries);
        self::assertSame(['PUT'], $entries[0]->methods);
        self::assertSame(['PATCH'], $entries[1]->methods);
    }

    #[Test]
    public function registerAddsRestApiInitHook(): void
    {
        if (!function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $controller = new #[Route('/items', namespace: 'test/v1')] #[Permission(public: true)] class {
            #[Route(methods: HttpMethod::GET)]
            public function list(): array
            {
                return [];
            }
        };

        $registry = new RestRegistry();
        $registry->register($controller);

        self::assertNotFalse(has_action('rest_api_init'));
    }
}
