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

namespace WpPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DataCollector\ContainerDataCollector;

final class ContainerDataCollectorTest extends TestCase
{
    #[Test]
    public function getNameReturnsContainer(): void
    {
        $collector = new ContainerDataCollector();

        self::assertSame('container', $collector->getName());
    }

    #[Test]
    public function getLabelReturnsContainer(): void
    {
        $collector = new ContainerDataCollector();

        self::assertSame('Container', $collector->getLabel());
    }

    #[Test]
    public function collectWithNullContainerReturnsDefaults(): void
    {
        $collector = new ContainerDataCollector();
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(0, $data['service_count']);
        self::assertSame(0, $data['public_count']);
        self::assertSame(0, $data['private_count']);
        self::assertSame(0, $data['autowired_count']);
        self::assertSame(0, $data['lazy_count']);
        self::assertSame([], $data['services']);
        self::assertSame([], $data['compiler_passes']);
        self::assertSame([], $data['tagged_services']);
        self::assertSame([], $data['parameters']);
    }

    #[Test]
    public function collectFromContainerWithDefinitions(): void
    {
        $definition = new class {
            public function isPublic(): bool
            {
                return true;
            }

            public function isAutowired(): bool
            {
                return true;
            }

            public function isLazy(): bool
            {
                return false;
            }

            public function getClass(): string
            {
                return 'App\\Service\\MyService';
            }

            public function getTags(): array
            {
                return ['app.service' => [['priority' => 10]]];
            }
        };

        $builder = new class ($definition) {
            public function __construct(private readonly object $definition) {}

            public function all(): array
            {
                return ['my_service' => $this->definition];
            }
        };

        $collector = new ContainerDataCollector($builder);
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(1, $data['service_count']);
        self::assertSame(1, $data['public_count']);
        self::assertSame(0, $data['private_count']);
        self::assertSame(1, $data['autowired_count']);
        self::assertSame(0, $data['lazy_count']);
        self::assertArrayHasKey('my_service', $data['services']);
        self::assertSame('App\\Service\\MyService', $data['services']['my_service']['class']);
        self::assertTrue($data['services']['my_service']['public']);
        self::assertTrue($data['services']['my_service']['autowired']);
        self::assertFalse($data['services']['my_service']['lazy']);
        self::assertSame(['app.service'], $data['services']['my_service']['tags']);
    }

    #[Test]
    public function getIndicatorValueReturnsServiceCount(): void
    {
        $collector = new ContainerDataCollector();

        $reflection = new \ReflectionProperty($collector, 'data');
        $reflection->setValue($collector, ['service_count' => 15]);

        self::assertSame('15', $collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenZero(): void
    {
        $collector = new ContainerDataCollector();

        $reflection = new \ReflectionProperty($collector, 'data');
        $reflection->setValue($collector, ['service_count' => 0]);

        self::assertSame('', $collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        $collector = new ContainerDataCollector();

        self::assertSame('default', $collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $collector = new ContainerDataCollector();
        $collector->collect();
        // Even default data is non-empty (has keys)
        // Set some data via reflection to ensure reset clears it
        $reflection = new \ReflectionProperty($collector, 'data');
        $reflection->setValue($collector, ['service_count' => 5]);
        self::assertNotEmpty($collector->getData());

        $collector->reset();

        self::assertEmpty($collector->getData());
    }

    #[Test]
    public function collectWithContainerBuilderGathersServices(): void
    {
        $definition1 = $this->createDefinition(
            class: 'App\\Service\\FooService',
            public: true,
            autowired: true,
            lazy: false,
            tags: ['app.handler' => [['priority' => 10]]],
        );

        $definition2 = $this->createDefinition(
            class: 'App\\Service\\BarService',
            public: false,
            autowired: false,
            lazy: true,
            tags: [],
        );

        $builder = new class ($definition1, $definition2) {
            public function __construct(
                private readonly object $def1,
                private readonly object $def2,
            ) {}

            /** @return array<string, object> */
            public function all(): array
            {
                return ['foo_service' => $this->def1, 'bar_service' => $this->def2];
            }
        };

        $collector = new ContainerDataCollector($builder);
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(2, $data['service_count']);
        self::assertArrayHasKey('foo_service', $data['services']);
        self::assertArrayHasKey('bar_service', $data['services']);
        self::assertSame('App\\Service\\FooService', $data['services']['foo_service']['class']);
        self::assertSame('App\\Service\\BarService', $data['services']['bar_service']['class']);
    }

    #[Test]
    public function collectWithContainerBuilderCountsPublicPrivate(): void
    {
        $publicDef = $this->createDefinition(class: 'App\\PublicService', public: true, autowired: false, lazy: false);
        $privateDef1 = $this->createDefinition(class: 'App\\PrivateService1', public: false, autowired: true, lazy: false);
        $privateDef2 = $this->createDefinition(class: 'App\\PrivateService2', public: false, autowired: false, lazy: true);

        $builder = new class ($publicDef, $privateDef1, $privateDef2) {
            public function __construct(
                private readonly object $pub,
                private readonly object $priv1,
                private readonly object $priv2,
            ) {}

            /** @return array<string, object> */
            public function all(): array
            {
                return [
                    'public_svc' => $this->pub,
                    'private_svc_1' => $this->priv1,
                    'private_svc_2' => $this->priv2,
                ];
            }
        };

        $collector = new ContainerDataCollector($builder);
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(3, $data['service_count']);
        self::assertSame(1, $data['public_count']);
        self::assertSame(2, $data['private_count']);
        self::assertSame(1, $data['autowired_count']);
        self::assertSame(1, $data['lazy_count']);
    }

    #[Test]
    public function collectWithContainerBuilderGathersCompilerPasses(): void
    {
        $pass1 = new class {};
        $pass2 = new class {};

        $passConfig = new class ($pass1, $pass2) {
            /** @var list<object> */
            private array $passes;

            public function __construct(object $pass1, object $pass2)
            {
                $this->passes = [$pass1, $pass2];
            }

            /** @return list<object> */
            public function getPasses(): array
            {
                return $this->passes;
            }
        };

        $builder = new class ($passConfig) {
            public function __construct(private readonly object $passConfig) {}

            /** @return array<string, object> */
            public function all(): array
            {
                return [];
            }

            public function getCompilerPassConfig(): object
            {
                return $this->passConfig;
            }
        };

        $collector = new ContainerDataCollector($builder);
        $collector->collect();
        $data = $collector->getData();

        self::assertCount(2, $data['compiler_passes']);
        self::assertIsString($data['compiler_passes'][0]);
        self::assertIsString($data['compiler_passes'][1]);
    }

    #[Test]
    public function collectWithContainerBuilderGathersTaggedServices(): void
    {
        $builder = new class {
            /** @return array<string, object> */
            public function all(): array
            {
                return [];
            }

            /** @return list<string> */
            public function findTags(): array
            {
                return ['kernel.event_listener', 'controller.argument_resolver'];
            }

            /**
             * @return array<string, list<array<string, mixed>>>
             */
            public function findTaggedServiceIds(string $tag): array
            {
                return match ($tag) {
                    'kernel.event_listener' => ['listener.a' => [], 'listener.b' => []],
                    'controller.argument_resolver' => ['resolver.a' => []],
                    default => [],
                };
            }
        };

        $collector = new ContainerDataCollector($builder);
        $collector->collect();
        $data = $collector->getData();

        self::assertArrayHasKey('kernel.event_listener', $data['tagged_services']);
        self::assertArrayHasKey('controller.argument_resolver', $data['tagged_services']);
        self::assertCount(2, $data['tagged_services']['kernel.event_listener']);
        self::assertCount(1, $data['tagged_services']['controller.argument_resolver']);
        self::assertContains('listener.a', $data['tagged_services']['kernel.event_listener']);
        self::assertContains('listener.b', $data['tagged_services']['kernel.event_listener']);
    }

    #[Test]
    public function collectWithContainerBuilderGathersParameters(): void
    {
        $parameterBag = new class {
            /** @return array<string, mixed> */
            public function all(): array
            {
                return [
                    'kernel.debug' => true,
                    'kernel.environment' => 'test',
                    'app.secret' => 's3cret',
                ];
            }
        };

        $builder = new class ($parameterBag) {
            public function __construct(private readonly object $bag) {}

            /** @return array<string, object> */
            public function all(): array
            {
                return [];
            }

            public function getParameterBag(): object
            {
                return $this->bag;
            }
        };

        $collector = new ContainerDataCollector($builder);
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(true, $data['parameters']['kernel.debug']);
        self::assertSame('test', $data['parameters']['kernel.environment']);
        self::assertSame('s3cret', $data['parameters']['app.secret']);
    }

    #[Test]
    public function collectWithSnapshotUsesSnapshotData(): void
    {
        $snapshot = [
            'service_count' => 3,
            'public_count' => 1,
            'private_count' => 2,
            'autowired_count' => 2,
            'lazy_count' => 0,
            'services' => [
                'App\\FooService' => [
                    'class' => 'App\\FooService',
                    'public' => true,
                    'autowired' => true,
                    'lazy' => false,
                    'tags' => ['app.handler'],
                ],
                'App\\BarService' => [
                    'class' => 'App\\BarService',
                    'public' => false,
                    'autowired' => true,
                    'lazy' => false,
                    'tags' => [],
                ],
                'App\\BazService' => [
                    'class' => 'App\\BazService',
                    'public' => false,
                    'autowired' => false,
                    'lazy' => false,
                    'tags' => [],
                ],
            ],
            'compiler_passes' => ['App\\SomePass'],
            'tagged_services' => ['app.handler' => ['App\\FooService']],
            'parameters' => ['debug' => true],
        ];

        $collector = new ContainerDataCollector();
        $collector->setContainerSnapshot($snapshot);
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(3, $data['service_count']);
        self::assertSame(1, $data['public_count']);
        self::assertSame(2, $data['private_count']);
        self::assertSame(2, $data['autowired_count']);
        self::assertArrayHasKey('App\\FooService', $data['services']);
        self::assertSame(['App\\SomePass'], $data['compiler_passes']);
        self::assertSame(['app.handler' => ['App\\FooService']], $data['tagged_services']);
        self::assertSame(['debug' => true], $data['parameters']);
    }

    #[Test]
    public function snapshotTakesPrecedenceOverBuilder(): void
    {
        $definition = $this->createDefinition(
            class: 'App\\Service\\MyService',
            public: true,
            autowired: true,
            lazy: false,
        );

        $builder = new class ($definition) {
            public function __construct(private readonly object $definition) {}

            /** @return array<string, object> */
            public function all(): array
            {
                return ['my_service' => $this->definition];
            }
        };

        $snapshot = [
            'service_count' => 10,
            'public_count' => 5,
            'private_count' => 5,
            'autowired_count' => 3,
            'lazy_count' => 1,
            'services' => [],
            'compiler_passes' => [],
            'tagged_services' => [],
            'parameters' => [],
        ];

        $collector = new ContainerDataCollector($builder);
        $collector->setContainerSnapshot($snapshot);
        $collector->collect();
        $data = $collector->getData();

        self::assertSame(10, $data['service_count']);
        self::assertSame(5, $data['public_count']);
    }

    #[Test]
    public function resetClearsSnapshot(): void
    {
        $snapshot = [
            'service_count' => 5,
            'public_count' => 2,
            'private_count' => 3,
            'autowired_count' => 1,
            'lazy_count' => 0,
            'services' => [],
            'compiler_passes' => [],
            'tagged_services' => [],
            'parameters' => [],
        ];

        $collector = new ContainerDataCollector();
        $collector->setContainerSnapshot($snapshot);
        $collector->collect();
        self::assertSame(5, $collector->getData()['service_count']);

        $collector->reset();
        self::assertEmpty($collector->getData());

        // After reset, collect should return zero defaults (no snapshot, no builder)
        $collector->collect();
        self::assertSame(0, $collector->getData()['service_count']);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $tags
     */
    private function createDefinition(
        string $class,
        bool $public,
        bool $autowired,
        bool $lazy,
        array $tags = [],
    ): object {
        return new class ($class, $public, $autowired, $lazy, $tags) {
            /**
             * @param array<string, list<array<string, mixed>>> $tags
             */
            public function __construct(
                private readonly string $class,
                private readonly bool $public,
                private readonly bool $autowired,
                private readonly bool $lazy,
                private readonly array $tags = [],
            ) {}

            public function isPublic(): bool
            {
                return $this->public;
            }

            public function isAutowired(): bool
            {
                return $this->autowired;
            }

            public function isLazy(): bool
            {
                return $this->lazy;
            }

            public function getClass(): string
            {
                return $this->class;
            }

            /**
             * @return array<string, list<array<string, mixed>>>
             */
            public function getTags(): array
            {
                return $this->tags;
            }
        };
    }
}
