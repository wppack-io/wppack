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

namespace WPPack\Plugin\PasskeyLoginPlugin\Profile;

use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;

final class PasskeyProfileSection
{
    private string $pluginFile;

    public function __construct(
        private readonly PasskeyLoginConfiguration $config,
    ) {}

    public function setPluginFile(string $pluginFile): void
    {
        $this->pluginFile = $pluginFile;
    }

    public function register(): void
    {
        // Enqueue assets early (admin_enqueue_scripts fires before page body)
        add_action('admin_enqueue_scripts', [$this, 'enqueueOnProfilePage']);

        // Render inside the form (show_user_profile), then JS relocates after the form
        add_action('show_user_profile', [$this, 'render']);
        add_action('edit_user_profile', [$this, 'render']);
    }

    public function enqueueOnProfilePage(string $hookSuffix): void
    {
        if ($hookSuffix !== 'profile.php' && $hookSuffix !== 'user-edit.php') {
            return;
        }

        $buildDir = \dirname($this->pluginFile) . '/js/build';
        $assetFile = $buildDir . '/profile.asset.php';

        if (!file_exists($assetFile)) {
            return;
        }

        /** @var array{dependencies: list<string>, version: string} $asset */
        $asset = require $assetFile;

        wp_enqueue_script(
            'wppack-passkey-login-profile',
            plugins_url('js/build/profile.js', $this->pluginFile),
            $asset['dependencies'],
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'wppack-passkey-login-profile',
            plugins_url('js/build/style-profile.css', $this->pluginFile),
            ['wp-components'],
            $asset['version'],
        );

        wp_set_script_translations(
            'wppack-passkey-login-profile',
            'wppack-passkey-login',
            \dirname($this->pluginFile) . '/languages',
        );
    }

    public function render(\WP_User $user): void
    {
        if (get_current_user_id() !== $user->ID && !current_user_can('manage_options')) {
            return;
        }

        wp_localize_script('wppack-passkey-login-profile', 'wppPasskeyProfile', [
            'restUrl' => rest_url('wppack/v1/passkey'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => $user->ID,
            'maxCredentials' => $this->config->maxCredentialsPerUser,
        ]);

        // Output inside the form; JS will relocate after the form within #wpbody-content
        echo '<div id="wppack-passkey-profile-wrapper" style="display:none">';
        echo '<h2>' . esc_html__('Passkeys', 'wppack-passkey-login') . '</h2>';
        echo '<div id="wppack-passkey-profile"></div>';
        echo '</div>';
    }
}
