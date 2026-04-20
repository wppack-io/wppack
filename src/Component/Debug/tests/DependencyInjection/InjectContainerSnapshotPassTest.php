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

namespace WPPack\Component\Debug\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\ContainerDataCollector;
use WPPack\Component\Debug\DependencyInjection\InjectContainerSnapshotPass;
use WPPack\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(InjectContainerSnapshotPass::class)]
final class InjectContainerSnapshotPassTest extends TestCase
{
    #[Test]
    public function passNoOpWhenContainerDataCollectorAbsent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('other.service');

        (new InjectContainerSnapshotPass())->process($builder);

        self::assertFalse($builder->hasDefinition(ContainerDataCollector::class));
    }

    #[Test]
    public function passInjectsSnapshotIntoCollectorViaSetter(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ContainerDataCollector::class);
        $builder->register('app.service')->addTag('app.tag');
        $builder->setParameter('app.environment', 'production');

        (new InjectContainerSnapshotPass())->process($builder);

        $calls = $builder->findDefinition(ContainerDataCollector::class)->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('setContainerSnapshot', $calls[0]['method']);

        $snapshot = $calls[0]['arguments'][0];
        self::assertIsArray($snapshot);
        self::assertArrayHasKey('service_count', $snapshot);
        self::assertArrayHasKey('services', $snapshot);
        self::assertArrayHasKey('tagged_services', $snapshot);
        self::assertArrayHasKey('parameters', $snapshot);
    }

    #[Test]
    public function snapshotCountsPublicAndAutowiredServices(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ContainerDataCollector::class);

        // ContainerBuilder::register() forces public=true by default
        $builder->register('app.public.one');
        $builder->register('app.public.two');

        (new InjectContainerSnapshotPass())->process($builder);

        $snapshot = $builder->findDefinition(ContainerDataCollector::class)
            ->getMethodCalls()[0]['arguments'][0];

        self::assertGreaterThanOrEqual(3, $snapshot['service_count']);
        self::assertArrayHasKey('ContainerDataCollector', array_combine(
            array_map(static fn(string $k): string => basename(str_replace('\\', '/', $k)), array_keys($snapshot['services'])),
            array_keys($snapshot['services']),
        ), 'ContainerDataCollector appears in services list');
    }

    #[Test]
    public function snapshotIncludesTaggedServicesGroupedByTag(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(ContainerDataCollector::class);
        $builder->register('a.voter')->addTag('security.voter');
        $builder->register('b.voter')->addTag('security.voter');
        $builder->register('handler')->addTag('messenger.handler');

        (new InjectContainerSnapshotPass())->process($builder);

        $snapshot = $builder->findDefinition(ContainerDataCollector::class)
            ->getMethodCalls()[0]['arguments'][0];

        self::assertArrayHasKey('security.voter', $snapshot['tagged_services']);
        self::assertCount(2, $snapshot['tagged_services']['security.voter']);
        self::assertArrayHasKey('messenger.handler', $snapshot['tagged_services']);
    }
}
