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

final class DefaultsConfiguratorTest extends TestCase
{
    #[Test]
    public function defaultsAreFalse(): void
    {
        $defaults = new DefaultsConfigurator();

        self::assertFalse($defaults->isAutowire());
        self::assertFalse($defaults->isPublic());
    }

    #[Test]
    public function setsAutowire(): void
    {
        $defaults = new DefaultsConfigurator();

        $result = $defaults->autowire();

        self::assertSame($defaults, $result);
        self::assertTrue($defaults->isAutowire());
    }

    #[Test]
    public function setsPublic(): void
    {
        $defaults = new DefaultsConfigurator();

        $result = $defaults->public();

        self::assertSame($defaults, $result);
        self::assertTrue($defaults->isPublic());
    }

    #[Test]
    public function setsAutowireFalse(): void
    {
        $defaults = new DefaultsConfigurator();
        $defaults->autowire();

        self::assertTrue($defaults->isAutowire());

        $defaults->autowire(false);

        self::assertFalse($defaults->isAutowire());
    }
}
