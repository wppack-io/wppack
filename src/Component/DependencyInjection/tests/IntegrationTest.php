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

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Dumper\PhpDumper;
use WpPack\Component\DependencyInjection\ServiceDiscovery;
use WpPack\Component\DependencyInjection\Tests\Fixtures\SimpleService;

final class IntegrationTest extends TestCase
{
    #[Test]
    public function fullWorkflowDiscoverCompileGet(): void
    {
        $builder = new ContainerBuilder();
        $discovery = new ServiceDiscovery($builder);

        $discovery->discover(
            __DIR__ . '/Fixtures',
            'WpPack\\Component\\DependencyInjection\\Tests\\Fixtures',
        );

        // Register dependencies needed by discovered fixtures
        $builder->register('custom.service', SimpleService::class)->setPublic(true)->autowire();
        $builder->setParameter('app.name', 'IntegrationTest');

        $container = $builder->compile();

        self::assertTrue($container->has(SimpleService::class));
        $service = $container->get(SimpleService::class);
        self::assertInstanceOf(SimpleService::class, $service);
        self::assertSame('hello', $service->hello());
    }

    #[Test]
    public function fullWorkflowWithCompilerPass(): void
    {
        $builder = new ContainerBuilder();

        $builder->register('handler.a', \stdClass::class)
            ->setPublic(true)
            ->addTag('app.handler');
        $builder->register('handler.b', \stdClass::class)
            ->setPublic(true)
            ->addTag('app.handler');
        $builder->register('manager', \stdClass::class)
            ->setPublic(true);

        $pass = new class implements CompilerPassInterface {
            /** @var list<string> */
            public array $collected = [];

            public function process(ContainerBuilder $builder): void
            {
                $taggedServices = $builder->findTaggedServiceIds('app.handler');
                foreach ($taggedServices as $id => $tags) {
                    $this->collected[] = $id;
                }
            }
        };

        $builder->addCompilerPass($pass);
        $container = $builder->compile();

        self::assertContains('handler.a', $pass->collected);
        self::assertContains('handler.b', $pass->collected);
        self::assertTrue($container->has('manager'));
    }

    #[Test]
    public function compileAndDumpRoundTrip(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(SimpleService::class, SimpleService::class)
            ->setPublic(true)
            ->autowire();
        $builder->setParameter('app.name', 'DumpTest');

        $builder->compile();

        $dumper = new PhpDumper($builder);
        $className = 'IntegrationDumpedContainer_' . uniqid();
        $code = $dumper->dump(['class' => $className]);

        eval('?>' . $code);

        /** @var \Psr\Container\ContainerInterface $container */
        $container = new $className();

        self::assertTrue($container->has(SimpleService::class));
        $service = $container->get(SimpleService::class);
        self::assertInstanceOf(SimpleService::class, $service);
        self::assertSame('hello', $service->hello());
    }
}
