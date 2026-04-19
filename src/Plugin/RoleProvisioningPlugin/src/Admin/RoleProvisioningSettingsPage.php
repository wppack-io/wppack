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

namespace WPPack\Plugin\RoleProvisioningPlugin\Admin;

use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-role-provisioning',
    label: 'Role Provisioning Settings',
    menuLabel: 'Role Provisioning',
    parent: 'options-general.php',
    position: 301,
    scope: AdminScope::Auto,
    textDomain: 'wppack-role-provisioning',
)]
#[IsGranted('manage_options')]
final class RoleProvisioningSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-role-provisioning-settings"></div>';
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
            'wppack-role-provisioning-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-role-provisioning-vendor',
            plugins_url('js/build/settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-role-provisioning-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components', 'wppack-role-provisioning-vendor'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-role-provisioning-settings',
            'wppack-role-provisioning',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-role-provisioning-settings', 'wppRoleProvisioning', [
            'restUrl' => rest_url('wppack/v1/role-provisioning'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
