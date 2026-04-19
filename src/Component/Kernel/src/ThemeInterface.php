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

namespace WPPack\Component\Kernel;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;

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
