<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\CommandRegistry;
use WpPack\Component\Console\DependencyInjection\CommandServiceProvider;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

#[CoversClass(CommandServiceProvider::class)]
final class CommandServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new CommandServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registerRegistersCommandRegistry(): void
    {
        $builder = new ContainerBuilder();
        $provider = new CommandServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(CommandRegistry::class));
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $builder = new ContainerBuilder();
        $provider = new CommandServiceProvider();

        $provider->register($builder);
        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(CommandRegistry::class));
    }
}
