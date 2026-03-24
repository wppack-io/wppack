<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\SettingsRegistry;
use WpPack\Component\Templating\TemplateRendererInterface;

final class SettingsRegistryTest extends TestCase
{
    private SettingsRegistry $registry;

    protected function setUp(): void
    {
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

    #[Test]
    public function registerSetsTemplateRendererWhenProvided(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new SettingsRegistry($renderer);

        $page = new RegistryTestSettingsPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractSettingsPage::class, 'templateRenderer');
        self::assertSame($renderer, $ref->getValue($page));
    }

    #[Test]
    public function registerMultiplePagesAddsMultipleHooks(): void
    {
        global $wp_filter;

        $countCallbacks = static function (string $hook) use (&$wp_filter): int {
            if (!isset($wp_filter[$hook])) {
                return 0;
            }
            $count = 0;
            foreach ($wp_filter[$hook]->callbacks as $priorityCallbacks) {
                $count += \count($priorityCallbacks);
            }
            return $count;
        };

        $adminMenuBefore = $countCallbacks('admin_menu');
        $adminInitBefore = $countCallbacks('admin_init');

        $page1 = new RegistryTestSettingsPage();
        $page2 = new RegistryTestSecondSettingsPage();

        $this->registry->register($page1);
        $this->registry->register($page2);

        self::assertSame($adminMenuBefore + 2, $countCallbacks('admin_menu'));
        self::assertSame($adminInitBefore + 2, $countCallbacks('admin_init'));
    }
}

#[AsSettingsPage(slug: 'registry-test', label: 'Registry Test')]
class RegistryTestSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('general', 'General')
            ->field('test_field', 'Test Field', fn(array $args) => null);
    }
}

#[AsSettingsPage(slug: 'registry-test-second', label: 'Registry Test Second')]
class RegistryTestSecondSettingsPage extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('options', 'Options')
            ->field('another_field', 'Another Field', fn(array $args) => null);
    }
}
