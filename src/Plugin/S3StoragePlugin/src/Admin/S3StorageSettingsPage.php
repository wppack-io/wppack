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

namespace WpPack\Plugin\S3StoragePlugin\Admin;

use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\Attribute\AdminScope;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Role\Attribute\IsGranted;

#[AsAdminPage(
    slug: 'wppack-storage',
    label: 'Storage Settings',
    menuLabel: 'Storage',
    parent: 'options-general.php',
    position: 102,
    scope: AdminScope::Auto,
    textDomain: 'wppack-storage',
)]
#[IsGranted('manage_options')]
final class S3StorageSettingsPage extends AbstractAdminPage
{
    private string $pluginFile;

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function __invoke(): string
    {
        return '<div id="wppack-storage-settings"></div>';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function awsRegions(): array
    {
        return [
            ['value' => 'us-east-1', 'label' => 'us-east-1 — US East (N. Virginia)'],
            ['value' => 'us-east-2', 'label' => 'us-east-2 — US East (Ohio)'],
            ['value' => 'us-west-1', 'label' => 'us-west-1 — US West (N. California)'],
            ['value' => 'us-west-2', 'label' => 'us-west-2 — US West (Oregon)'],
            ['value' => 'us-gov-east-1', 'label' => 'us-gov-east-1 — AWS GovCloud (US-East)'],
            ['value' => 'us-gov-west-1', 'label' => 'us-gov-west-1 — AWS GovCloud (US-West)'],
            ['value' => 'af-south-1', 'label' => 'af-south-1 — Africa (Cape Town)'],
            ['value' => 'ap-east-1', 'label' => 'ap-east-1 — Asia Pacific (Hong Kong)'],
            ['value' => 'ap-east-2', 'label' => 'ap-east-2 — Asia Pacific (Taipei)'],
            ['value' => 'ap-south-1', 'label' => 'ap-south-1 — Asia Pacific (Mumbai)'],
            ['value' => 'ap-south-2', 'label' => 'ap-south-2 — Asia Pacific (Hyderabad)'],
            ['value' => 'ap-southeast-1', 'label' => 'ap-southeast-1 — Asia Pacific (Singapore)'],
            ['value' => 'ap-southeast-2', 'label' => 'ap-southeast-2 — Asia Pacific (Sydney)'],
            ['value' => 'ap-southeast-3', 'label' => 'ap-southeast-3 — Asia Pacific (Jakarta)'],
            ['value' => 'ap-southeast-4', 'label' => 'ap-southeast-4 — Asia Pacific (Melbourne)'],
            ['value' => 'ap-southeast-5', 'label' => 'ap-southeast-5 — Asia Pacific (Malaysia)'],
            ['value' => 'ap-southeast-6', 'label' => 'ap-southeast-6 — Asia Pacific (New Zealand)'],
            ['value' => 'ap-southeast-7', 'label' => 'ap-southeast-7 — Asia Pacific (Thailand)'],
            ['value' => 'ap-northeast-1', 'label' => 'ap-northeast-1 — Asia Pacific (Tokyo)'],
            ['value' => 'ap-northeast-2', 'label' => 'ap-northeast-2 — Asia Pacific (Seoul)'],
            ['value' => 'ap-northeast-3', 'label' => 'ap-northeast-3 — Asia Pacific (Osaka)'],
            ['value' => 'ca-central-1', 'label' => 'ca-central-1 — Canada (Central)'],
            ['value' => 'ca-west-1', 'label' => 'ca-west-1 — Canada West (Calgary)'],
            ['value' => 'cn-north-1', 'label' => 'cn-north-1 — China (Beijing)'],
            ['value' => 'cn-northwest-1', 'label' => 'cn-northwest-1 — China (Ningxia)'],
            ['value' => 'eu-central-1', 'label' => 'eu-central-1 — Europe (Frankfurt)'],
            ['value' => 'eu-central-2', 'label' => 'eu-central-2 — Europe (Zurich)'],
            ['value' => 'eu-west-1', 'label' => 'eu-west-1 — Europe (Ireland)'],
            ['value' => 'eu-west-2', 'label' => 'eu-west-2 — Europe (London)'],
            ['value' => 'eu-west-3', 'label' => 'eu-west-3 — Europe (Paris)'],
            ['value' => 'eu-south-1', 'label' => 'eu-south-1 — Europe (Milan)'],
            ['value' => 'eu-south-2', 'label' => 'eu-south-2 — Europe (Spain)'],
            ['value' => 'eu-north-1', 'label' => 'eu-north-1 — Europe (Stockholm)'],
            ['value' => 'eusc-de-east-1', 'label' => 'eusc-de-east-1 — European Sovereign Cloud (Germany)'],
            ['value' => 'il-central-1', 'label' => 'il-central-1 — Israel (Tel Aviv)'],
            ['value' => 'mx-central-1', 'label' => 'mx-central-1 — Mexico (Central)'],
            ['value' => 'me-south-1', 'label' => 'me-south-1 — Middle East (Bahrain)'],
            ['value' => 'me-central-1', 'label' => 'me-central-1 — Middle East (UAE)'],
            ['value' => 'sa-east-1', 'label' => 'sa-east-1 — South America (São Paulo)'],
        ];
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
            'wppack-storage-settings',
            plugins_url('js/build/settings.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-storage-vendor',
            plugins_url('js/build/settings.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_enqueue_style(
            'wppack-storage-settings',
            plugins_url('js/build/style-settings.css', $this->pluginFile),
            ['wp-components', 'wppack-storage-vendor'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-storage-settings',
            'wppack-storage',
            \dirname($this->pluginFile) . '/languages',
        );

        wp_localize_script('wppack-storage-settings', 'wppStorage', [
            'restUrl' => rest_url('wppack/v1/storage'),
            'nonce' => wp_create_nonce('wp_rest'),
            'awsRegions' => self::awsRegions(),
        ]);
    }
}
