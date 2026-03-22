<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel;

use WpPack\Component\DependencyInjection\Container;

abstract class AbstractPlugin implements PluginInterface
{
    public function __construct(
        private readonly string $pluginFile,
    ) {}

    public function getFile(): string
    {
        return $this->pluginFile;
    }

    public function getPath(): string
    {
        return plugin_dir_path($this->pluginFile);
    }

    public function getUrl(): string
    {
        return plugin_dir_url($this->pluginFile);
    }

    public function getBasename(): string
    {
        return plugin_basename($this->pluginFile);
    }

    public function getCompilerPasses(): array
    {
        return [];
    }

    public function boot(Container $container): void {}

    public function onActivate(): void {}

    public function onDeactivate(): void {}
}
