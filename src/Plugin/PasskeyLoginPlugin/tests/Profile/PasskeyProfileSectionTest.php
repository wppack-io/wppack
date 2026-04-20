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

namespace WPPack\Plugin\PasskeyLoginPlugin\Tests\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;
use WPPack\Plugin\PasskeyLoginPlugin\Profile\PasskeyProfileSection;

#[CoversClass(PasskeyProfileSection::class)]
final class PasskeyProfileSectionTest extends TestCase
{
    private int $userId = 0;

    protected function setUp(): void
    {
        $this->userId = (int) wp_insert_user([
            'user_login' => 'pk_profile_' . uniqid(),
            'user_email' => 'pk_profile_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);
    }

    protected function tearDown(): void
    {
        wp_set_current_user(0);
        wp_delete_user($this->userId);
        remove_all_actions('admin_enqueue_scripts');
        remove_all_actions('show_user_profile');
        remove_all_actions('edit_user_profile');
    }

    private function section(?PasskeyLoginConfiguration $config = null): PasskeyProfileSection
    {
        return new PasskeyProfileSection($config ?? new PasskeyLoginConfiguration());
    }

    #[Test]
    public function registerWiresExpectedHooks(): void
    {
        $section = $this->section();
        $section->register();

        self::assertNotFalse(has_action('admin_enqueue_scripts', [$section, 'enqueueOnProfilePage']));
        self::assertNotFalse(has_action('show_user_profile', [$section, 'render']));
        self::assertNotFalse(has_action('edit_user_profile', [$section, 'render']));
    }

    #[Test]
    public function enqueueNoOpsOnNonProfilePage(): void
    {
        $section = $this->section();
        $section->setPluginFile('/nonexistent/wppack-passkey-login.php');

        // Should not throw despite missing asset file, because hook-suffix check fires first
        $section->enqueueOnProfilePage('dashboard');

        self::assertFalse(wp_script_is('wppack-passkey-login-profile', 'enqueued'));
    }

    #[Test]
    public function enqueueNoOpsWhenAssetFileMissing(): void
    {
        $section = $this->section();
        $section->setPluginFile('/nonexistent/wppack-passkey-login.php');

        $section->enqueueOnProfilePage('profile.php');

        self::assertFalse(wp_script_is('wppack-passkey-login-profile', 'enqueued'));
    }

    #[Test]
    public function renderSilentlySkipsWhenViewingOtherUserWithoutAdminCap(): void
    {
        wp_set_current_user($this->userId); // regular user, not admin

        $other = (int) wp_insert_user([
            'user_login' => 'pk_other_' . uniqid(),
            'user_email' => 'pk_other_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
        ]);

        ob_start();
        $this->section()->render(new \WP_User($other));
        $out = (string) ob_get_clean();

        self::assertSame('', $out);

        wp_delete_user($other);
    }

    #[Test]
    public function renderEmitsWrapperMarkupForOwnProfile(): void
    {
        wp_set_current_user($this->userId);

        ob_start();
        $this->section()->render(new \WP_User($this->userId));
        $out = (string) ob_get_clean();

        self::assertStringContainsString('wppack-passkey-profile-wrapper', $out);
        self::assertStringContainsString('wppack-passkey-profile', $out);
        self::assertStringContainsString('Passkeys', $out);
    }

    #[Test]
    public function renderEmitsMarkupForAdminViewingOtherUser(): void
    {
        $admin = (int) wp_insert_user([
            'user_login' => 'admin_' . uniqid(),
            'user_email' => 'admin_' . uniqid() . '@example.com',
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
        ]);
        wp_set_current_user($admin);

        ob_start();
        $this->section()->render(new \WP_User($this->userId));
        $out = (string) ob_get_clean();

        self::assertStringContainsString('wppack-passkey-profile', $out);

        wp_delete_user($admin);
    }

    #[Test]
    public function enqueueRegistersScriptAndStyleWhenAssetFileExists(): void
    {
        // Simulate a plugin layout with js/build/profile.asset.php on disk.
        $pluginDir = sys_get_temp_dir() . '/wppack-passkey-profile-' . uniqid();
        $buildDir = $pluginDir . '/js/build';
        mkdir($buildDir, 0o755, true);
        file_put_contents(
            $buildDir . '/profile.asset.php',
            "<?php return ['dependencies' => ['wp-element'], 'version' => '1.2.3'];",
        );
        $pluginFile = $pluginDir . '/wppack-passkey-login.php';
        file_put_contents($pluginFile, '<?php // test plugin entrypoint');

        try {
            $section = $this->section();
            $section->setPluginFile($pluginFile);

            $section->enqueueOnProfilePage('profile.php');

            self::assertTrue(wp_script_is('wppack-passkey-login-profile', 'enqueued'));
            self::assertTrue(wp_style_is('wppack-passkey-login-profile', 'enqueued'));
        } finally {
            wp_dequeue_script('wppack-passkey-login-profile');
            wp_dequeue_style('wppack-passkey-login-profile');
            wp_deregister_script('wppack-passkey-login-profile');
            wp_deregister_style('wppack-passkey-login-profile');
            @unlink($buildDir . '/profile.asset.php');
            @unlink($pluginFile);
            @rmdir($buildDir);
            @rmdir($pluginDir . '/js');
            @rmdir($pluginDir);
        }
    }
}
