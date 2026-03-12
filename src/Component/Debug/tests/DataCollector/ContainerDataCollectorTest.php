<?php

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

            public function getDefinitions(): array
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
    public function getBadgeValueReturnsServiceCount(): void
    {
        $collector = new ContainerDataCollector();

        $reflection = new \ReflectionProperty($collector, 'data');
        $reflection->setValue($collector, ['service_count' => 15]);

        self::assertSame('15', $collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeValueReturnsEmptyWhenZero(): void
    {
        $collector = new ContainerDataCollector();

        $reflection = new \ReflectionProperty($collector, 'data');
        $reflection->setValue($collector, ['service_count' => 0]);

        self::assertSame('', $collector->getBadgeValue());
    }

    #[Test]
    public function getBadgeColorReturnsDefault(): void
    {
        $collector = new ContainerDataCollector();

        self::assertSame('default', $collector->getBadgeColor());
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
}
