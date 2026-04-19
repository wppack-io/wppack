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

namespace WPPack\Component\Templating\Bridge\Twig\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Templating\Bridge\Twig\DependencyInjection\TwigTemplatingServiceProvider;
use WPPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;
use WPPack\Component\Templating\Bridge\Twig\TwigEnvironmentFactory;
use WPPack\Component\Templating\Bridge\Twig\TwigRenderer;
use WPPack\Component\Templating\ChainRenderer;
use WPPack\Component\Templating\DependencyInjection\TemplatingServiceProvider;
use WPPack\Component\Templating\PhpRenderer;
use WPPack\Component\Templating\TemplateRendererInterface;

final class TwigTemplatingServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new TwigTemplatingServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersTwigRenderer(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));

        self::assertTrue($builder->hasDefinition(TwigRenderer::class));
        self::assertTrue($builder->hasDefinition(TwigEnvironmentFactory::class));
        self::assertTrue($builder->hasDefinition(WordPressExtension::class));
        self::assertTrue($builder->hasDefinition(Environment::class));
    }

    #[Test]
    public function registersChainRendererWhenPhpRendererExists(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));

        self::assertTrue($builder->hasDefinition(ChainRenderer::class));
    }

    #[Test]
    public function setsAliasToChainRenderer(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));

        $container = $builder->compile();
        $renderer = $container->get(TemplateRendererInterface::class);

        self::assertInstanceOf(ChainRenderer::class, $renderer);
    }

    #[Test]
    public function setsAliasToTwigRendererStandalone(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));

        $container = $builder->compile();
        $renderer = $container->get(TemplateRendererInterface::class);

        self::assertInstanceOf(TwigRenderer::class, $renderer);
    }

    #[Test]
    public function passesPathsToFactory(): void
    {
        $path = __DIR__ . '/../Fixtures/templates';
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [$path],
        ));

        $definition = $builder->findDefinition(TwigEnvironmentFactory::class);
        $arguments = $definition->getArguments();

        self::assertSame([$path], $arguments[0]);
    }

    #[Test]
    public function passesTwigOptions(): void
    {
        $options = ['debug' => true, 'cache' => '/tmp/twig'];
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
            twigOptions: $options,
        ));

        $definition = $builder->findDefinition(TwigEnvironmentFactory::class);
        $arguments = $definition->getArguments();

        self::assertSame($options, $arguments[1]);
    }

    #[Test]
    public function compiledContainerResolves(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new TwigTemplatingServiceProvider(
            paths: [__DIR__ . '/../Fixtures/templates'],
        ));

        $container = $builder->compile();

        $renderer = $container->get(TwigRenderer::class);
        self::assertInstanceOf(TwigRenderer::class, $renderer);

        $html = $renderer->render('simple', ['title' => 'DI Test']);
        self::assertStringContainsString('DI Test', $html);
    }
}
