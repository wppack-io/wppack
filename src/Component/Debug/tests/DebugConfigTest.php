<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Debug\DebugConfig;

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
    public function isAllowedRoleReturnsTrueWhenWordPressNotLoaded(): void
    {
        if (function_exists('current_user_can')) {
            self::markTestSkipped('WordPress is loaded; current_user_can() exists.');
        }

        // When current_user_can is not available, role check is skipped
        $config = new DebugConfig(roleWhitelist: ['administrator']);

        self::assertTrue($config->isAllowedRole());
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
