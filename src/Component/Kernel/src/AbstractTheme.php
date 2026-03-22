<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel;

use WpPack\Component\DependencyInjection\Container;

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
