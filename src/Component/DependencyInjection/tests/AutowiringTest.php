<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Tests\Fixtures\DependentService;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SampleImplementation;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SampleInterface;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

final class AutowiringTest extends TestCase
{
    #[Test]
    public function resolvesConcreteTypeDependency(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(SimpleService::class, SimpleService::class)
            ->setPublic(true)
            ->autowire();
        $builder->register(DependentService::class, DependentService::class)
            ->setPublic(true)
            ->autowire();

        $container = $builder->compile();

        $dependent = $container->get(DependentService::class);
        self::assertInstanceOf(DependentService::class, $dependent);
        self::assertInstanceOf(SimpleService::class, $dependent->simple);
    }

    #[Test]
    public function resolvesInterfaceDependencyViaAlias(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(SampleImplementation::class, SampleImplementation::class)
            ->setPublic(true)
            ->autowire();
        $builder->setAlias(SampleInterface::class, SampleImplementation::class);

        $container = $builder->compile();

        $service = $container->get(SampleInterface::class);
        self::assertInstanceOf(SampleImplementation::class, $service);
        self::assertSame('sample', $service->getValue());
    }

    #[Test]
    public function resolvesParameterValue(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('app.name', 'TestApp');
        $builder->register('param.service', \stdClass::class)
            ->setPublic(true)
            ->setArgument(0, '%app.name%');

        $container = $builder->compile();

        $service = $container->get('param.service');
        self::assertInstanceOf(\stdClass::class, $service);
    }
}
