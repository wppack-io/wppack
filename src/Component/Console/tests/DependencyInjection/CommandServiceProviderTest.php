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

namespace WPPack\Component\Console\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\CommandRegistry;
use WPPack\Component\Console\DependencyInjection\CommandServiceProvider;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;

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
