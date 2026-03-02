<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\HookServiceProvider;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;

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
