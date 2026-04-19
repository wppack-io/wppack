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

namespace WPPack\Plugin\MonitoringPlugin\Admin;

use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Monitoring\MonitoringCollector;
use WPPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-monitoring',
    label: 'Infrastructure Monitoring',
    menuLabel: 'Monitoring',
    icon: 'dashicons-chart-area',
    position: 90,
    scope: AdminScope::Auto,
    textDomain: 'wppack-monitoring',
)]
#[IsGranted('manage_options')]
final class MonitoringDashboardPage extends AbstractAdminPage
{
    private string $pluginFile;
    private MonitoringCollector $collector;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function setCollector(MonitoringCollector $collector): void
    {
        $this->collector = $collector;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-monitoring-dashboard"></div>';
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
            'wppack-monitoring-vendor',
            plugins_url('js/build/dashboard.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-monitoring-dashboard',
            plugins_url('js/build/style-dashboard.css', $this->pluginFile),
            ['wp-components', 'wppack-monitoring-vendor'],
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
            'bridges' => $this->collector->getBridgeMetadata(),
        ]);
    }
}
