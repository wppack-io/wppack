<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Bridge\Twig\DependencyInjection;

use Twig\Environment;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;
use WpPack\Component\Templating\Bridge\Twig\TwigEnvironmentFactory;
use WpPack\Component\Templating\Bridge\Twig\TwigRenderer;
use WpPack\Component\Templating\ChainRenderer;
use WpPack\Component\Templating\PhpRenderer;
use WpPack\Component\Templating\TemplateRendererInterface;

final class TwigTemplatingServiceProvider implements ServiceProviderInterface
{
    /**
     * @param list<string>         $paths
     * @param array<string, mixed> $twigOptions
     */
    public function __construct(
        private readonly array $paths = [],
        private readonly array $twigOptions = [],
        private readonly bool $useChainRenderer = true,
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(WordPressExtension::class)
            ->autowire();

        $builder->register(TwigEnvironmentFactory::class)
            ->addArgument($this->paths)
            ->addArgument($this->twigOptions)
            ->addArgument([new Reference(WordPressExtension::class)]);

        $builder->register(Environment::class)
            ->setFactory([new Reference(TwigEnvironmentFactory::class), 'create']);

        $builder->register(TwigRenderer::class)
            ->addArgument(new Reference(Environment::class));

        if ($this->useChainRenderer && $builder->hasDefinition(PhpRenderer::class)) {
            $builder->register(ChainRenderer::class)
                ->addArgument([
                    new Reference(PhpRenderer::class),
                    new Reference(TwigRenderer::class),
                ]);

            $builder->setAlias(TemplateRendererInterface::class, ChainRenderer::class);
        } else {
            $builder->setAlias(TemplateRendererInterface::class, TwigRenderer::class);
        }
    }
}
