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

namespace WPPack\Component\Hook\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\DependencyInjection\HookServiceProvider;
use WPPack\Component\Hook\HookDiscovery;
use WPPack\Component\Hook\HookRegistry;

final class HookServiceProviderTest extends TestCase
{
    #[Test]
    public function registersHookRegistry(): void
    {
        $builder = new ContainerBuilder();
        $provider = new HookServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(HookRegistry::class));
    }

    #[Test]
    public function registersHookDiscovery(): void
    {
        $builder = new ContainerBuilder();
        $provider = new HookServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(HookDiscovery::class));
    }

    #[Test]
    public function hookDiscoveryHasRegistryArgument(): void
    {
        $builder = new ContainerBuilder();
        $provider = new HookServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(HookDiscovery::class);
        $arguments = $definition->getArguments();

        self::assertCount(1, $arguments);
    }
}
