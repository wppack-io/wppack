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

namespace WPPack\Component\SiteHealth\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\SiteHealth\DependencyInjection\SiteHealthServiceProvider;
use WPPack\Component\SiteHealth\SiteHealthRegistry;

final class SiteHealthServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new SiteHealthServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersSiteHealthRegistry(): void
    {
        $builder = new ContainerBuilder();
        $provider = new SiteHealthServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(SiteHealthRegistry::class));
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new SiteHealthServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(SiteHealthRegistry::class));
    }

    #[Test]
    public function compiledContainerResolvesSiteHealthRegistry(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new SiteHealthServiceProvider());

        $container = $builder->compile();

        self::assertTrue($container->has(SiteHealthRegistry::class));
        self::assertInstanceOf(SiteHealthRegistry::class, $container->get(SiteHealthRegistry::class));
    }
}
