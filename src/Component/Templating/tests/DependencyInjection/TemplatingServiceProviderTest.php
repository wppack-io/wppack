<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Templating\DependencyInjection\TemplatingServiceProvider;
use WpPack\Component\Templating\PhpRenderer;
use WpPack\Component\Templating\TemplateLocator;
use WpPack\Component\Templating\TemplateRendererInterface;

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
