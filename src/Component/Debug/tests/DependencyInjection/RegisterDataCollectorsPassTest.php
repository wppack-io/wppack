<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DependencyInjection\RegisterDataCollectorsPass;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Tests\DependencyInjection\Fixtures\DefaultPriorityCollector;
use WpPack\Component\Debug\Tests\DependencyInjection\Fixtures\HighPriorityCollector;
use WpPack\Component\Debug\Tests\DependencyInjection\Fixtures\LowPriorityCollector;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;

final class RegisterDataCollectorsPassTest extends TestCase
{
    #[Test]
    public function processReturnsEarlyWhenDefinitionMissing(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterDataCollectorsPass();

        // Profile::class is not registered, so process() should return early
        $pass->process($builder);

        // No definitions were modified — nothing to assert beyond no exception thrown
        self::assertFalse($builder->hasDefinition(Profile::class));
    }

    #[Test]
    public function processRegistersTaggedServices(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Profile::class, Profile::class);
        $builder->register(HighPriorityCollector::class, HighPriorityCollector::class);

        $pass = new RegisterDataCollectorsPass();
        $pass->process($builder);

        $profileDefinition = $builder->findDefinition(Profile::class);
        $methodCalls = $profileDefinition->getMethodCalls();

        self::assertCount(1, $methodCalls);
        self::assertSame('addCollector', $methodCalls[0]['method']);
        self::assertCount(1, $methodCalls[0]['arguments']);

        $reference = $methodCalls[0]['arguments'][0];
        self::assertInstanceOf(Reference::class, $reference);
        self::assertSame(HighPriorityCollector::class, $reference->getId());
    }

    #[Test]
    public function processSortsByPriority(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Profile::class, Profile::class);

        // Register in non-priority order
        $builder->register(LowPriorityCollector::class, LowPriorityCollector::class);
        $builder->register(HighPriorityCollector::class, HighPriorityCollector::class);
        $builder->register(DefaultPriorityCollector::class, DefaultPriorityCollector::class);

        $pass = new RegisterDataCollectorsPass();
        $pass->process($builder);

        $profileDefinition = $builder->findDefinition(Profile::class);
        $methodCalls = $profileDefinition->getMethodCalls();

        self::assertCount(3, $methodCalls);

        // Sorted by priority descending: 100, 0, -10
        $ids = array_map(
            static fn(array $call): string => $call['arguments'][0]->getId(),
            $methodCalls,
        );

        self::assertSame([
            HighPriorityCollector::class,    // priority 100
            DefaultPriorityCollector::class, // priority 0
            LowPriorityCollector::class,     // priority -10
        ], $ids);

        // All method calls should be addCollector
        foreach ($methodCalls as $call) {
            self::assertSame('addCollector', $call['method']);
        }
    }

    #[Test]
    public function processSkipsNonExistentClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Profile::class, Profile::class);

        // Register a definition with a non-existent class
        $builder->register('non_existent_service', 'App\\NonExistent\\FakeCollector');

        // Register a valid collector
        $builder->register(HighPriorityCollector::class, HighPriorityCollector::class);

        $pass = new RegisterDataCollectorsPass();
        $pass->process($builder);

        $profileDefinition = $builder->findDefinition(Profile::class);
        $methodCalls = $profileDefinition->getMethodCalls();

        // Only the valid collector should be registered
        self::assertCount(1, $methodCalls);
        self::assertSame(HighPriorityCollector::class, $methodCalls[0]['arguments'][0]->getId());
    }

    #[Test]
    public function processRegistersServiceWithTagOnly(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Profile::class, Profile::class);

        // Register a collector using tag (HighPriorityCollector also has the attribute,
        // but the tag detection path is also exercised)
        $builder->register(HighPriorityCollector::class, HighPriorityCollector::class)
            ->addTag(RegisterDataCollectorsPass::TAG);

        $pass = new RegisterDataCollectorsPass();
        $pass->process($builder);

        $profileDefinition = $builder->findDefinition(Profile::class);
        $methodCalls = $profileDefinition->getMethodCalls();

        self::assertCount(1, $methodCalls);
        self::assertSame('addCollector', $methodCalls[0]['method']);
    }

    #[Test]
    public function tagConstantHasExpectedValue(): void
    {
        self::assertSame('debug.data_collector', RegisterDataCollectorsPass::TAG);
    }
}
