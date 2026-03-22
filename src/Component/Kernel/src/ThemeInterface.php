<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

interface ThemeInterface extends ServiceProviderInterface
{
    public function getFile(): string;

    public function getPath(): string;

    public function getUrl(): string;

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array;

    public function boot(Container $container): void;
}
