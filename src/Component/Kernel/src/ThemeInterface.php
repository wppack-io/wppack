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
