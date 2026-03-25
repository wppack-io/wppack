<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\EventDispatcher;

final class EventDispatcherServiceProviderTest extends TestCase
{
    #[Test]
    public function registersEventDispatcher(): void
    {
        $builder = new ContainerBuilder();
        $provider = new EventDispatcherServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(EventDispatcher::class));
    }

    #[Test]
    public function registersPsr14Alias(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(EventDispatcher::class);
        $provider = new EventDispatcherServiceProvider();

        $provider->register($builder);

        $container = $builder->compile();

        self::assertTrue($container->has(EventDispatcherInterface::class));
    }
}
