<?php

declare(strict_types=1);

namespace WpPack\Component\Asset;

final class AssetManager
{
    /**
     * @param string[]                $deps
     * @param string|bool|null        $ver
     * @param array<string, mixed>|bool $args
     */
    public function registerScript(string $handle, string|false $src, array $deps = [], string|bool|null $ver = false, array|bool $args = []): bool
    {
        return wp_register_script($handle, $src, $deps, $ver, $args);
    }

    /**
     * @param string[]                $deps
     * @param string|bool|null        $ver
     * @param array<string, mixed>|bool $args
     */
    public function enqueueScript(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, array|bool $args = []): void
    {
        wp_enqueue_script($handle, $src, $deps, $ver, $args);
    }

    public function dequeueScript(string $handle): void
    {
        wp_dequeue_script($handle);
    }

    public function deregisterScript(string $handle): void
    {
        wp_deregister_script($handle);
    }

    public function scriptIs(string $handle, string $status = 'enqueued'): bool
    {
        return wp_script_is($handle, $status);
    }

    public function addInlineScript(string $handle, string $data, string $position = 'after'): bool
    {
        return wp_add_inline_script($handle, $data, $position);
    }

    /**
     * @param array<string, mixed> $l10n
     */
    public function localizeScript(string $handle, string $objectName, array $l10n): bool
    {
        return wp_localize_script($handle, $objectName, $l10n);
    }

    /**
     * @param string[]         $deps
     * @param string|bool|null $ver
     */
    public function registerStyle(string $handle, string|false $src, array $deps = [], string|bool|null $ver = false, string $media = 'all'): bool
    {
        return wp_register_style($handle, $src, $deps, $ver, $media);
    }

    /**
     * @param string[]         $deps
     * @param string|bool|null $ver
     */
    public function enqueueStyle(string $handle, string $src = '', array $deps = [], string|bool|null $ver = false, string $media = 'all'): void
    {
        wp_enqueue_style($handle, $src, $deps, $ver, $media);
    }

    public function dequeueStyle(string $handle): void
    {
        wp_dequeue_style($handle);
    }

    public function deregisterStyle(string $handle): void
    {
        wp_deregister_style($handle);
    }

    public function styleIs(string $handle, string $status = 'enqueued'): bool
    {
        return wp_style_is($handle, $status);
    }

    public function addInlineStyle(string $handle, string $data): bool
    {
        return wp_add_inline_style($handle, $data);
    }
}
