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
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Exception\ServiceNotFoundException;

final class ContainerTest extends TestCase
{
    #[Test]
    public function getsService(): void
    {
        $container = $this->createContainer();

        $service = $container->get('my.service');
        self::assertInstanceOf(\stdClass::class, $service);
    }

    #[Test]
    public function checksServiceExists(): void
    {
        $container = $this->createContainer();

        self::assertTrue($container->has('my.service'));
        self::assertFalse($container->has('missing.service'));
    }

    #[Test]
    public function throwsServiceNotFoundException(): void
    {
        $container = $this->createContainer();

        $this->expectException(ServiceNotFoundException::class);
        $container->get('missing.service');
    }

    #[Test]
    public function implementsPsrContainer(): void
    {
        $container = $this->createContainer();

        self::assertInstanceOf(\Psr\Container\ContainerInterface::class, $container);
    }

    private function createContainer(): Container
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class)->setPublic(true);

        return $builder->compile();
    }
}
