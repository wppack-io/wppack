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

namespace WPPack\Component\Templating\DependencyInjection;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Escaper\Escaper;
use WPPack\Component\Templating\PhpRenderer;
use WPPack\Component\Templating\TemplateLocator;
use WPPack\Component\Templating\TemplateRendererInterface;

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
