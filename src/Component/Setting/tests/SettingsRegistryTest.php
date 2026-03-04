<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\SettingsRegistry;

final class SettingsRegistryTest extends TestCase
{
    private SettingsRegistry $registry;

    protected function setUp(): void
    {
        if (!\function_exists('add_action')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->registry = new SettingsRegistry();
    }

    #[Test]
    public function registerCompletesWithoutException(): void
    {
        $page = new RegistryTestSettingsPage();

        $this->registry->register($page);

        self::assertTrue(true);
    }

    #[Test]
    public function registerAddsAdminMenuHook(): void
    {
        $page = new RegistryTestSettingsPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function registerAddsAdminInitHook(): void
    {
        $page = new RegistryTestSettingsPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_init'));
    }
}

#[AsSettingsPage(slug: 'registry-test', title: 'Registry Test')]
class RegistryTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('general', 'General')
            ->field('test_field', 'Test Field', fn(array $args) => null);
    }
}
