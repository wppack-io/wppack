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

namespace WPPack\Plugin\AmazonMailerPlugin\Admin;

use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-mailer',
    label: 'Mail Settings',
    menuLabel: 'Mail',
    parent: 'options-general.php',
    position: 103,
    scope: AdminScope::Auto,
    textDomain: 'wppack-mailer',
)]
#[IsGranted('manage_options')]
final class AmazonMailerSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-mailer-settings"></div>';
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
            'wppack-mailer-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-mailer-vendor',
            plugins_url('js/build/settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-mailer-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components', 'wppack-mailer-vendor'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-mailer-settings',
            'wppack-mailer',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-mailer-settings', 'wppMailer', [
            'restUrl' => rest_url('wppack/v1/mailer'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
