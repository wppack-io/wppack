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

namespace WpPack\Plugin\ScimPlugin\Admin;

use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AdminScope;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-scim',
    label: 'SCIM Settings',
    menuLabel: 'SCIM',
    parent: 'options-general.php',
    position: 300,
    scope: AdminScope::Network,
)]
#[IsGranted('manage_options')]
final class ScimSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-scim-settings"></div>';
    }

    protected function enqueue(): void
    {
        $buildDir = \dirname($this->pluginFile) . '/js/build';
        $assetFile = $buildDir . '/settings.asset.php';

        if (!file_exists($assetFile)) {
            return;
        }

        /** @var array{dependencies: list<string>, version: string} $asset */
        $asset = require $assetFile;

        wp_enqueue_script(
            'wppack-scim-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-scim-vendor',
            plugins_url('js/build/settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-scim-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components', 'wppack-scim-vendor'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-scim-settings',
            'wppack-scim',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-scim-settings', 'wppScim', [
            'restUrl' => rest_url('wppack/v1/scim'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
