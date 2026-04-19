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

namespace WPPack\Component\Templating\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Escaper\Escaper;
use WPPack\Component\Templating\DependencyInjection\TemplatingServiceProvider;
use WPPack\Component\Templating\PhpRenderer;
use WPPack\Component\Templating\TemplateLocator;
use WPPack\Component\Templating\TemplateRendererInterface;

final class TemplatingServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new TemplatingServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersEscaper(): void
    {
        $builder = new ContainerBuilder();
        $provider = new TemplatingServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(Escaper::class));
    }

    #[Test]
    public function doesNotOverrideExistingEscaper(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(Escaper::class)->addTag('existing');

        $provider = new TemplatingServiceProvider();
        $provider->register($builder);

        $definition = $builder->findDefinition(Escaper::class);
        self::assertTrue($definition->hasTag('existing'));
    }

    #[Test]
    public function registersTemplateLocator(): void
    {
        $builder = new ContainerBuilder();
        $provider = new TemplatingServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(TemplateLocator::class));
    }

    #[Test]
    public function registersPhpRenderer(): void
    {
        $builder = new ContainerBuilder();
        $provider = new TemplatingServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(PhpRenderer::class));
    }

    #[Test]
    public function phpRendererIsAutowired(): void
    {
        $builder = new ContainerBuilder();
        $provider = new TemplatingServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(PhpRenderer::class);
        self::assertTrue($definition->isAutowired());
    }

    #[Test]
    public function passesPathsToLocator(): void
    {
        $builder = new ContainerBuilder();
        $paths = ['/templates', '/theme'];
        $provider = new TemplatingServiceProvider($paths);

        $provider->register($builder);

        $definition = $builder->findDefinition(TemplateLocator::class);
        self::assertSame([$paths], $definition->getArguments());
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new TemplatingServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(PhpRenderer::class));
    }
}
