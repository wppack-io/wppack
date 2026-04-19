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

namespace WPPack\Plugin\PasskeyLoginPlugin\Admin;

use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-passkey-login',
    label: 'Passkey Login Settings',
    menuLabel: 'Passkey Login',
    parent: 'options-general.php',
    position: 203,
    scope: AdminScope::Auto,
    textDomain: 'wppack-passkey-login',
)]
#[IsGranted('manage_options')]
final class PasskeyLoginSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-passkey-login-settings"></div>';
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
            'wppack-passkey-login-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-passkey-login-vendor',
            plugins_url('js/build/settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-passkey-login-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components', 'wppack-passkey-login-vendor'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-passkey-login-settings',
            'wppack-passkey-login',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-passkey-login-settings', 'wppPasskeyLogin', [
            'restUrl' => rest_url('wppack/v1/passkey-login'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
