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

namespace WPPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Tests\Fixtures\DependentService;
use WPPack\Component\DependencyInjection\Tests\Fixtures\SampleImplementation;
use WPPack\Component\DependencyInjection\Tests\Fixtures\SampleInterface;
use WPPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

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
