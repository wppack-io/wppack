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

namespace WpPack\MuPlugin\MultisiteUrlFixer;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\Kernel\AbstractPlugin;
use WpPack\MuPlugin\MultisiteUrlFixer\Subscriber\UrlFixerSubscriber;

final class MultisiteUrlFixerPlugin extends AbstractPlugin
{
    public function __construct(
        string $pluginFile,
        private readonly string $wpPath,
    ) {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(EventDispatcher::class)) {
            (new EventDispatcherServiceProvider())->register($builder);
        }

        $builder->register(UrlFixerSubscriber::class)
            ->addArgument($this->wpPath);
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return [
            new RegisterEventListenersPass(),
        ];
    }
}
