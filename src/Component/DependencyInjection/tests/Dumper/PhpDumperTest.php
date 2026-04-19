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

namespace WPPack\Component\DependencyInjection\Tests\Dumper;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Dumper\PhpDumper;

final class PhpDumperTest extends TestCase
{
    #[Test]
    public function dumpsCompiledContainer(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class)->setPublic(true);
        $builder->compile();

        $dumper = new PhpDumper($builder);
        $code = $dumper->dump(['class' => 'TestContainer']);

        self::assertStringContainsString('class TestContainer', $code);
    }

    #[Test]
    public function dumpsWithDefaultOptions(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class)->setPublic(true);
        $builder->compile();

        $dumper = new PhpDumper($builder);
        $code = $dumper->dump();

        self::assertStringContainsString('class ProjectServiceContainer', $code);
    }

    #[Test]
    public function dumpedContainerIsUsable(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.service', \stdClass::class)->setPublic(true);
        $builder->compile();

        $dumper = new PhpDumper($builder);
        $className = 'DumpedContainer_' . uniqid();
        $code = $dumper->dump(['class' => $className]);

        eval('?>' . $code);

        /** @var \Psr\Container\ContainerInterface $container */
        $container = new $className();

        self::assertTrue($container->has('my.service'));
        self::assertInstanceOf(\stdClass::class, $container->get('my.service'));
    }
}
