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

namespace WpPack\Plugin\SamlLoginPlugin\Admin;

use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AdminScope;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-saml-login',
    label: 'SAML Login Settings',
    menuLabel: 'SAML Login',
    parent: 'options-general.php',
    position: 201,
    scope: AdminScope::Auto,
    textDomain: 'wppack-saml-login',
)]
#[IsGranted('manage_options')]
final class SamlLoginSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-saml-login-settings"></div>';
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
            'wppack-saml-login-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-saml-login-vendor',
            plugins_url('js/build/settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-saml-login-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components', 'wppack-saml-login-vendor'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-saml-login-settings',
            'wppack-saml-login',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-saml-login-settings', 'wppSamlLogin', [
            'restUrl' => rest_url('wppack/v1/saml-login'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
