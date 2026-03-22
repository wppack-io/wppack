<?php

declare(strict_types=1);

namespace WpPack\Component\Plugin;

final readonly class PluginPathResolver
{
    public function __construct(
        private string $pluginFile,
    ) {}

    /**
     * @see plugin_dir_url()
     */
    public function getUrl(): string
    {
        return plugin_dir_url($this->pluginFile);
    }

    /**
     * @see plugin_dir_path()
     */
    public function getPath(): string
    {
        return plugin_dir_path($this->pluginFile);
    }

    /**
     * @see plugin_basename()
     */
    public function getBasename(): string
    {
        return plugin_basename($this->pluginFile);
    }
}
