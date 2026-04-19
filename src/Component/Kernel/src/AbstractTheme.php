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

use WPPack\Component\DependencyInjection\Container;

abstract class AbstractTheme implements ThemeInterface
{
    public function __construct(
        private readonly string $themeFile,
    ) {}

    public function getFile(): string
    {
        return $this->themeFile;
    }

    public function getPath(): string
    {
        return trailingslashit(dirname($this->themeFile));
    }

    public function getUrl(): string
    {
        $slug = wp_basename(dirname($this->themeFile));

        return trailingslashit(get_theme_root_uri($slug) . '/' . $slug);
    }

    public function getCompilerPasses(): array
    {
        return [];
    }

    public function boot(Container $container): void {}
}
