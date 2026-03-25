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

namespace WpPack\Component\DependencyInjection\Tests\Configurator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Configurator\DefaultsConfigurator;
use WpPack\Component\DependencyInjection\Configurator\PrototypeConfigurator;
use WpPack\Component\DependencyInjection\ContainerBuilder;

final class PrototypeConfiguratorTest extends TestCase
{
    #[Test]
    public function processDiscoversServices(): void
    {
        $builder = new ContainerBuilder();
        $defaults = new DefaultsConfigurator();
        $defaults->autowire()->public();
        $configurator = new PrototypeConfigurator(
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\',
            __DIR__ . '/../Fixtures',
        );
        $configurator->exclude('Config/*');

        $configurator->process($builder, $defaults);

        self::assertTrue($builder->hasDefinition(
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\SimpleService',
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
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\',
            __DIR__ . '/../Fixtures',
        );
        $configurator->exclude('Config/*');

        $configurator->process($builder, $defaults);

        $definition = $builder->findDefinition(
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures\\SimpleService',
        );
        self::assertFalse($definition->isAutowired());
        self::assertFalse($definition->isPublic());
    }
}
