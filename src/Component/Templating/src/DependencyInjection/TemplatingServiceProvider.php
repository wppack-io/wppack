<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Escaper\Escaper;
use WpPack\Component\Templating\PhpRenderer;
use WpPack\Component\Templating\TemplateLocator;
use WpPack\Component\Templating\TemplateRendererInterface;

final class TemplatingServiceProvider implements ServiceProviderInterface
{
    /** @param list<string> $paths Template search paths */
    public function __construct(
        private readonly array $paths = [],
    ) {}

    public function register(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(Escaper::class)) {
            $builder->register(Escaper::class);
        }

        $builder->register(TemplateLocator::class)
            ->addArgument($this->paths);

        $builder->register(PhpRenderer::class)
            ->autowire();

        $builder->setAlias(TemplateRendererInterface::class, PhpRenderer::class);
    }
}
