<?php

declare(strict_types=1);

namespace WpPack\Component\Asset;

final class AssetManager
{
    /**
     * @param string[]                $deps
     * @param array<string, mixed>|bool $args
     *
     * @see wp_register_script()
     */
    public function registerScript(string $handle, string|false $src, array $deps = [], string|bool $ver = false, array|bool $args = []): bool
    {
        return wp_register_script($handle, $src, $deps, $ver, $args);
    }

    /**
     * @param string[]                $deps
     * @param array<string, mixed>|bool $args
     *
     * @see wp_enqueue_script()
     */
    public function enqueueScript(string $handle, string $src = '', array $deps = [], string|bool $ver = false, array|bool $args = []): void
    {
        wp_enqueue_script($handle, $src, $deps, $ver, $args);
    }

    /**
     * @see wp_dequeue_script()
     */
    public function dequeueScript(string $handle): void
    {
        wp_dequeue_script($handle);
    }

    /**
     * @see wp_deregister_script()
     */
    public function deregisterScript(string $handle): void
    {
        wp_deregister_script($handle);
    }

    /**
     * @see wp_script_is()
     */
    public function scriptIs(string $handle, string $status = 'enqueued'): bool
    {
        return wp_script_is($handle, $status);
    }

    /**
     * @see wp_add_inline_script()
     */
    public function addInlineScript(string $handle, string $data, string $position = 'after'): bool
    {
        return wp_add_inline_script($handle, $data, $position);
    }

    /**
     * @param array<string, mixed> $l10n
     *
     * @see wp_localize_script()
     */
    public function localizeScript(string $handle, string $objectName, array $l10n): bool
    {
        return wp_localize_script($handle, $objectName, $l10n);
    }

    /**
     * @param string[] $deps
     *
     * @see wp_register_style()
     */
    public function registerStyle(string $handle, string|false $src, array $deps = [], string|bool $ver = false, string $media = 'all'): bool
    {
        return wp_register_style($handle, $src, $deps, $ver, $media);
    }

    /**
     * @param string[] $deps
     *
     * @see wp_enqueue_style()
     */
    public function enqueueStyle(string $handle, string $src = '', array $deps = [], string|bool $ver = false, string $media = 'all'): void
    {
        wp_enqueue_style($handle, $src, $deps, $ver, $media);
    }

    /**
     * @see wp_dequeue_style()
     */
    public function dequeueStyle(string $handle): void
    {
        wp_dequeue_style($handle);
    }

    /**
     * @see wp_deregister_style()
     */
    public function deregisterStyle(string $handle): void
    {
        wp_deregister_style($handle);
    }

    /**
     * @see wp_style_is()
     */
    public function styleIs(string $handle, string $status = 'enqueued'): bool
    {
        return wp_style_is($handle, $status);
    }

    /**
     * @see wp_add_inline_style()
     */
    public function addInlineStyle(string $handle, string $data): bool
    {
        return wp_add_inline_style($handle, $data);
    }
}
