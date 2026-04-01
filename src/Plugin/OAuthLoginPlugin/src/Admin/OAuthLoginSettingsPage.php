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

namespace WpPack\Plugin\OAuthLoginPlugin\Admin;

use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AdminScope;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-oauth-login',
    label: 'OAuth Login Settings',
    menuLabel: 'OAuth Login',
    parent: 'options-general.php',
    position: 200,
    scope: AdminScope::Auto,
)]
#[IsGranted('manage_options')]
final class OAuthLoginSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div class="wrap"><div id="wppack-oauth-login-settings"></div></div>';
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
            'wppack-oauth-login-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-oauth-login-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-oauth-login-settings',
            'wppack-oauth-login',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-oauth-login-settings', 'wppOAuthLogin', [
            'restUrl' => rest_url('wppack/v1/oauth-login'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
