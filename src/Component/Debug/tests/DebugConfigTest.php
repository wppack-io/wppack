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
}
