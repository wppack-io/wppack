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

namespace WpPack\Plugin\MonitoringPlugin\Admin;

use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AdminScope;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-monitoring',
    label: 'Infrastructure Monitoring',
    menuLabel: 'Monitoring',
    icon: 'dashicons-chart-area',
    position: 90,
    scope: AdminScope::Auto,
)]
#[IsGranted('manage_options')]
final class MonitoringDashboardPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div class="wrap"><div id="wppack-monitoring-dashboard"></div></div>';
    }

    protected function enqueue(): void
    {
        $buildDir = \dirname($this->pluginFile) . '/js/build';
        $assetFile = $buildDir . '/dashboard.asset.php';

        if (!file_exists($assetFile)) {
            return;
        }

        /** @var array{dependencies: list<string>, version: string} $asset */
        $asset = require $assetFile;

        wp_enqueue_script(
            'wppack-monitoring-dashboard',
            plugins_url('js/build/dashboard.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-monitoring-dashboard',
            plugins_url('js/build/style-dashboard.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-monitoring-dashboard',
            'wppack-monitoring',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-monitoring-dashboard', 'wppMonitoring', [
            'restUrl' => rest_url('wppack/v1/monitoring'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
