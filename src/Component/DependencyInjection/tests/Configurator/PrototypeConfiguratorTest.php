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

namespace WPPack\Component\DependencyInjection\Tests\Configurator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\Configurator\DefaultsConfigurator;
use WPPack\Component\DependencyInjection\Configurator\PrototypeConfigurator;
use WPPack\Component\DependencyInjection\ContainerBuilder;

final class PrototypeConfiguratorTest extends TestCase
{
    #[Test]
    public function processDiscoversServices(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $defaults->autowire()->public();
        $configurator = new PrototypeConfigurator(
            'WPPack\\Component\\DependencyInjection\\Tests\\Fixtures\\',
            __DIR__ . '/../Fixtures',
        );
        $configurator->exclude('Config/*');

        $configurator->process($builder, $defaults);

        self::assertTrue($builder->hasDefinition(
            'WPPack\\Component\\DependencyInjection\\Tests\\Fixtures\\SimpleService',
        ));
    }

    #[Test]
    public function excludeAddsPatterns(): void
    {
        $configurator = new PrototypeConfigurator(
            'App\\',
            '/src',
        );

        $result = $configurator->exclude('Tests/*', 'Fixtures/*');

        self::assertSame($configurator, $result);
    }

    #[Test]
    public function processPassesDefaultsToDiscovery(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $defaults->autowire(false)->public(false);
        $configurator = new PrototypeConfigurator(
            'WPPack\\Component\\DependencyInjection\\Tests\\Fixtures\\',
            __DIR__ . '/../Fixtures',
        );
        $configurator->exclude('Config/*');

        $configurator->process($builder, $defaults);

        $definition = $builder->findDefinition(
            'WPPack\\Component\\DependencyInjection\\Tests\\Fixtures\\SimpleService',
        );
        self::assertFalse($definition->isAutowired());
        self::assertFalse($definition->isPublic());
    }
}
