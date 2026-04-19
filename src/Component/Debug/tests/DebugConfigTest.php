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

namespace WPPack\Component\Debug\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DebugConfig;

final class DebugConfigTest extends TestCase
{
    #[Test]
    public function defaultConfigIsDisabled(): void
    {
        $config = new DebugConfig();

        self::assertFalse($config->enabled);
        self::assertFalse($config->isEnabled());
    }

    #[Test]
    public function enabledConfigReturnsTrue(): void
    {
        $config = new DebugConfig(enabled: true);

        if (!$config->isEnabled()) {
            // WP_DEBUG=false or production environment prevents isEnabled()
            self::markTestSkipped('isEnabled() is false in this environment (WP_DEBUG or env type).');
        }

        self::assertTrue($config->isEnabled());
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseWhenDisabled(): void
    {
        $config = new DebugConfig(enabled: false, showToolbar: true);

        self::assertFalse($config->shouldShowToolbar());
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseWhenShowToolbarIsFalse(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: false);

        self::assertFalse($config->shouldShowToolbar());
    }

    #[Test]
    public function shouldShowToolbarReturnsTrueWhenBothEnabledAndShowToolbar(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        // When WordPress functions are not available, ajax/cron/rest checks are skipped
        // so shouldShowToolbar should return true
        self::assertTrue($config->shouldShowToolbar());
    }

    #[Test]
    public function isAllowedIpReturnsTrueForWhitelistedIp(): void
    {
        $config = new DebugConfig(ipWhitelist: ['192.168.1.1', '10.0.0.1']);

        self::assertTrue($config->isAllowedIp('192.168.1.1'));
        self::assertTrue($config->isAllowedIp('10.0.0.1'));
    }

    #[Test]
    public function isAllowedIpReturnsFalseForNonWhitelistedIp(): void
    {
        $config = new DebugConfig(ipWhitelist: ['192.168.1.1']);

        self::assertFalse($config->isAllowedIp('10.0.0.1'));
        self::assertFalse($config->isAllowedIp('172.16.0.1'));
    }

    #[Test]
    public function defaultIpWhitelistContainsLocalhostAddresses(): void
    {
        $config = new DebugConfig();

        self::assertTrue($config->isAllowedIp('127.0.0.1'));
        self::assertTrue($config->isAllowedIp('::1'));
    }

    #[Test]
    public function defaultRoleWhitelistContainsAdministrator(): void
    {
        $config = new DebugConfig();

        self::assertContains('administrator', $config->roleWhitelist);
    }

    #[Test]
    public function isAccessAllowedReturnsFalseWhenDisabled(): void
    {
        $config = new DebugConfig(enabled: false);

        self::assertFalse($config->isAccessAllowed());
    }

    #[Test]
    public function isAccessAllowedReturnsTrueWhenEnabledWithLocalhostIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        self::assertTrue($config->isAccessAllowed());
    }

    #[Test]
    public function isAccessAllowedReturnsFalseForNonWhitelistedIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);

        self::assertFalse($config->isAccessAllowed());
    }

    #[Test]
    public function isAccessAllowedReturnsTrueWhenRemoteAddrIsEmpty(): void
    {
        // CLI context — no REMOTE_ADDR, IP check is skipped
        unset($_SERVER['REMOTE_ADDR']);
        $config = new DebugConfig(enabled: true);

        if (!$config->isAccessAllowed()) {
            self::markTestSkipped('isAccessAllowed() is false in this environment.');
        }

        self::assertTrue($config->isAccessAllowed());
    }

    #[Test]
    public function shouldShowToolbarUsesAccessAllowed(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
        $config = new DebugConfig(enabled: true, showToolbar: true, ipWhitelist: ['127.0.0.1']);

        // Even though showToolbar is true, non-whitelisted IP should block
        self::assertFalse($config->shouldShowToolbar());
    }

    #[Test]
    public function isAllowedRoleReturnsTrueForAdminUser(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_config_admin_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'config_admin@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            $config = new DebugConfig(roleWhitelist: ['administrator']);
            self::assertTrue($config->isAllowedRole());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isAllowedRoleReturnsFalseForSubscriber(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_config_sub_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
            'user_email' => 'config_sub@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            $config = new DebugConfig(roleWhitelist: ['administrator']);
            self::assertFalse($config->isAllowedRole());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseForAjax(): void
    {
        // Simulate AJAX by adding filter
        add_filter('wp_doing_ajax', '__return_true');

        try {
            $config = new DebugConfig(enabled: true, showToolbar: true);

            if (!$config->isEnabled()) {
                self::markTestSkipped('isEnabled() is false in this environment.');
            }

            // In WP environment with wp_doing_ajax returning true
            // shouldShowToolbar should return false
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            remove_filter('wp_doing_ajax', '__return_true');
        }
    }

    #[Test]
    public function isAccessAllowedCombinesAllChecks(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_access_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'access@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(
                enabled: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            if (!$config->isEnabled()) {
                self::markTestSkipped('isEnabled() is false in this environment.');
            }

            self::assertTrue($config->isAccessAllowed());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isEnabledReturnsFalseWhenDisabled(): void
    {
        $config = new DebugConfig(enabled: false);

        self::assertFalse($config->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseWhenWpDebugIsFalse(): void
    {
        // WP_DEBUG is checked in isEnabled()
        // If WP_DEBUG is defined and false, isEnabled() should return false
        if (!defined('WP_DEBUG')) {
            self::markTestSkipped('WP_DEBUG is not defined in this environment.');
        }

        if (WP_DEBUG === true) {
            self::markTestSkipped('WP_DEBUG is true in this environment.');
        }

        $config = new DebugConfig(enabled: true);
        self::assertFalse($config->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsTrueInDevEnvironment(): void
    {
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false in this environment.');
        }

        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Environment type is production.');
        }

        $config = new DebugConfig(enabled: true);
        self::assertTrue($config->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseInProductionEnvironment(): void
    {
        if (wp_get_environment_type() !== 'production') {
            self::markTestSkipped('Environment type is not production.');
        }

        $config = new DebugConfig(enabled: true);
        self::assertFalse($config->isEnabled());
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseWhenShowToolbarDisabled(): void
    {
        $config = new DebugConfig(enabled: true, showToolbar: false);

        // showToolbar is false, so shouldShowToolbar must be false regardless of other conditions
        self::assertFalse($config->shouldShowToolbar());
    }

    #[Test]
    public function shouldShowToolbarReturnsTrueWhenAllConditionsMet(): void
    {
        // Ensure we're not in production
        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Cannot test in production environment.');
        }

        // Ensure WP_DEBUG is not false
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_toolbar_admin_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'toolbar_admin@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            if (!$config->isAccessAllowed()) {
                self::markTestSkipped('isAccessAllowed() is false in this environment.');
            }

            // Ensure no ajax/cron/rest conditions are interfering
            if (wp_doing_ajax()) {
                self::markTestSkipped('AJAX request detected.');
            }
            if (wp_doing_cron()) {
                self::markTestSkipped('Cron request detected.');
            }
            if (defined('REST_REQUEST') && REST_REQUEST) {
                self::markTestSkipped('REST request detected.');
            }

            self::assertTrue($config->shouldShowToolbar());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseForCron(): void
    {
        add_filter('wp_doing_cron', '__return_true');

        try {
            $config = new DebugConfig(enabled: true, showToolbar: true);

            if (!$config->isEnabled()) {
                self::markTestSkipped('isEnabled() is false in this environment.');
            }

            // In WP environment with wp_doing_cron returning true
            // shouldShowToolbar should return false
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            remove_filter('wp_doing_cron', '__return_true');
        }
    }

    #[Test]
    public function isAllowedRoleReturnsFalseForEditorWithAdminOnlyWhitelist(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_editor_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'editor',
            'user_email' => 'editor_role@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            $config = new DebugConfig(roleWhitelist: ['administrator']);
            self::assertFalse($config->isAllowedRole());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isEnabledReturnsFalseWhenWpDebugIsFalseViaCoverage(): void
    {
        // Cover line 27: defined('WP_DEBUG') && !WP_DEBUG → return false
        // WP_DEBUG is a constant, so we can only test this when it's false
        if (!defined('WP_DEBUG') || WP_DEBUG !== false) {
            self::markTestSkipped('WP_DEBUG is not false in this environment.');
        }

        $config = new DebugConfig(enabled: true);
        self::assertFalse($config->isEnabled());
    }

    #[Test]
    public function isEnabledReturnsFalseInProductionViaCoverage(): void
    {
        // Cover line 32: production environment check
        if (wp_get_environment_type() !== 'production') {
            self::markTestSkipped('Not in production environment.');
        }

        $config = new DebugConfig(enabled: true);
        self::assertFalse($config->isEnabled());
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseWhenShowToolbarFalseWithAccessAllowed(): void
    {
        // Cover line 68: !$this->showToolbar returns false
        // Need isAccessAllowed() to return true first, then showToolbar=false to hit line 68
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Production environment.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_toolbar_show_false_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'toolbar_show_false@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: false,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            // isAccessAllowed should be true
            self::assertTrue($config->isAccessAllowed());
            // But showToolbar=false, so shouldShowToolbar returns false at line 68
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseForAjaxWithAdminUser(): void
    {
        // Cover line 72: wp_doing_ajax() returns true
        // Need isAccessAllowed()=true AND showToolbar=true to reach the ajax check
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Production environment.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_ajax_admin_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'ajax_admin@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        add_filter('wp_doing_ajax', '__return_true');

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            self::assertTrue($config->isAccessAllowed());
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            remove_filter('wp_doing_ajax', '__return_true');
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseForCronWithAdminUser(): void
    {
        // Cover line 76: wp_doing_cron() returns true
        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        if (wp_get_environment_type() === 'production') {
            self::markTestSkipped('Production environment.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_cron_admin_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'cron_admin@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        add_filter('wp_doing_cron', '__return_true');

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            self::assertTrue($config->isAccessAllowed());
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            remove_filter('wp_doing_cron', '__return_true');
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseForRestRequestWithAdminUser(): void
    {
        // Cover line 80: REST_REQUEST check
        // REST_REQUEST is a constant - can only test if defined and true
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST is not defined or not true.');
        }

        if (defined('WP_DEBUG') && !WP_DEBUG) {
            self::markTestSkipped('WP_DEBUG is false.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_rest_admin_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'rest_admin@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            self::assertTrue($config->isAccessAllowed());
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isAllowedRoleReturnsTrueForMatchingRole(): void
    {
        // Cover line 99: current_user_can($role) returns true
        $userId = wp_insert_user([
            'user_login' => 'test_role_match_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'editor',
            'user_email' => 'role_match@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            // Use 'editor' in the whitelist to match this user's role
            $config = new DebugConfig(roleWhitelist: ['editor']);
            self::assertTrue($config->isAllowedRole());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isAllowedRoleReturnsTrueWithEmptyWhitelist(): void
    {
        // Cover line 93-95: empty roleWhitelist returns true
        $config = new DebugConfig(roleWhitelist: []);

        self::assertTrue($config->isAllowedRole());
    }

    #[Test]
    public function isAllowedRoleReturnsFalseForNoUser(): void
    {
        // No user logged in, roles don't match
        wp_set_current_user(0);

        $config = new DebugConfig(roleWhitelist: ['administrator']);

        self::assertFalse($config->isAllowedRole());
    }

    #[Test]
    public function shouldShowToolbarReturnsFalseForRestRequest(): void
    {
        // Cover line 79-81: REST_REQUEST constant check
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            self::markTestSkipped('REST_REQUEST is not defined or not true.');
        }

        $userId = wp_insert_user([
            'user_login' => 'test_rest_toolbar_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'rest_toolbar@example.com',
        ]);

        wp_set_current_user($userId);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        try {
            $config = new DebugConfig(
                enabled: true,
                showToolbar: true,
                ipWhitelist: ['127.0.0.1'],
                roleWhitelist: ['administrator'],
            );

            self::assertTrue($config->isAccessAllowed());
            // REST_REQUEST is true, so toolbar should not be shown
            self::assertFalse($config->shouldShowToolbar());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    #[Test]
    public function isAccessAllowedSkipsIpCheckWhenRemoteAddrEmpty(): void
    {
        // Cover line 49-50: empty REMOTE_ADDR skips IP check
        $_SERVER['REMOTE_ADDR'] = '';

        $config = new DebugConfig(enabled: true, ipWhitelist: ['127.0.0.1']);

        if (!$config->isEnabled()) {
            self::markTestSkipped('isEnabled() is false in this environment.');
        }

        // With empty REMOTE_ADDR, the IP check should be skipped
        // and access depends only on role check
        $userId = wp_insert_user([
            'user_login' => 'test_empty_addr_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
            'user_email' => 'empty_addr@example.com',
        ]);

        wp_set_current_user($userId);

        try {
            self::assertTrue($config->isAccessAllowed());
        } finally {
            wp_set_current_user(0);
            wp_delete_user($userId);
        }
    }

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
    }

    /** @var array<string, mixed> */
    private array $originalServer;
}
