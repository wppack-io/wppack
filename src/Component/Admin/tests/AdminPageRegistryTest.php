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

namespace WPPack\Component\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Admin\AbstractAdminPage;
use WPPack\Component\Admin\AdminPageRegistry;
use WPPack\Component\Admin\Attribute\AdminScope;
use WPPack\Component\Admin\Attribute\AsAdminPage;
use WPPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistryTest extends TestCase
{
    private AdminPageRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AdminPageRegistry();
    }

    protected function tearDown(): void
    {
        global $submenu, $wp_filter;
        $submenu = [];
        unset($wp_filter['admin_menu'], $wp_filter['network_admin_menu']);
    }

    #[Test]
    public function registerCompletesWithoutException(): void
    {
        $page = new RegistryTestAdminPage();

        $this->registry->register($page);

        self::assertTrue(true);
    }

    #[Test]
    public function registerAddsAdminMenuHook(): void
    {
        $page = new RegistryTestAdminPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function registerAddsEnqueueHookWhenOverridden(): void
    {
        $page = new RegistryEnqueueTestAdminPage();

        $this->registry->register($page);

        self::assertNotFalse(has_action('admin_enqueue_scripts'));
    }

    #[Test]
    public function registerDoesNotAddEnqueueHookWhenNotOverridden(): void
    {
        // Reset admin_enqueue_scripts hooks
        global $wp_filter;
        unset($wp_filter['admin_enqueue_scripts']);

        $page = new RegistryTestAdminPage();

        $this->registry->register($page);

        self::assertFalse(has_action('admin_enqueue_scripts'));
    }

    #[Test]
    public function unregisterCallsRemoveMenuPage(): void
    {
        $this->registry->unregister('registry-test-admin');

        self::assertTrue(true);
    }

    #[Test]
    public function unregisterSubmenuCallsRemoveSubmenuPage(): void
    {
        $this->registry->unregisterSubmenu('options-general.php', 'registry-test-admin');

        self::assertTrue(true);
    }

    #[Test]
    public function registerSetsTemplateRendererWhenProvided(): void
    {
        $renderer = $this->createMock(TemplateRendererInterface::class);
        $registry = new AdminPageRegistry($renderer);

        $page = new RegistryTestAdminPage();
        $registry->register($page);

        $ref = new \ReflectionProperty(AbstractAdminPage::class, 'renderer');
        self::assertSame($renderer, $ref->getValue($page));
    }

    #[Test]
    public function registerWithNetworkTrueUsesNetworkAdminMenuHook(): void
    {
        global $wp_filter;
        unset($wp_filter['network_admin_menu']);

        $page = new RegistryAutoScopedAdminPage();

        $this->registry->register($page, true);

        self::assertNotFalse(has_action('network_admin_menu'));
    }

    #[Test]
    public function registerWithNetworkFalseUsesAdminMenuHook(): void
    {
        global $wp_filter;
        unset($wp_filter['admin_menu']);

        $page = new RegistryAutoScopedAdminPage();

        $this->registry->register($page, false);

        self::assertNotFalse(has_action('admin_menu'));
    }

    #[Test]
    public function registerNetworkScopedPageAlwaysUsesNetworkHook(): void
    {
        global $wp_filter;
        unset($wp_filter['network_admin_menu']);

        $page = new RegistryNetworkScopedAdminPage();

        // Even though $network=false, the Network scope should force network_admin_menu
        $this->registry->register($page, false);

        self::assertNotFalse(has_action('network_admin_menu'));
    }

    #[Test]
    public function sortSubmenuReordersWpPackItemsByRegisteredPosition(): void
    {
        $registry = new AdminPageRegistry();

        // Register three WPPack pages with explicit positions so the
        // registry records them in submenuPositions.
        $registry->register(new RegistryPositionedThirdPage());
        $registry->register(new RegistryPositionedFirstPage());
        $registry->register(new RegistryPositionedSecondPage());

        // Simulate WordPress having populated $submenu as it would after
        // admin_menu ran: a core item then WPPack pages in registration
        // order.
        global $submenu;
        $submenu['options-general.php'] = [
            ['Core Option', 'manage_options', 'options-general.php'],
            ['Third', 'manage_options', 'wppack-third'],
            ['First', 'manage_options', 'wppack-first'],
            ['Second', 'manage_options', 'wppack-second'],
        ];

        // Invoke the private sortSubmenu pass directly — we don't want to
        // fire admin_menu again because that would re-run add_menu_page
        // and duplicate the rows we just seeded.
        (new \ReflectionMethod($registry, 'sortSubmenu'))->invoke($registry);

        self::assertSame(
            ['options-general.php', 'wppack-first', 'wppack-second', 'wppack-third'],
            array_column($submenu['options-general.php'], 2),
        );
    }

    #[Test]
    public function sortSubmenuSkipsParentsWithoutRegisteredEntries(): void
    {
        $registry = new AdminPageRegistry();
        $registry->register(new RegistryPositionedStandalonePage());

        global $submenu;
        $submenu = [];

        // Parent key missing from $submenu → sortSubmenu foreach takes the
        // `continue` branch without touching anything.
        (new \ReflectionMethod($registry, 'sortSubmenu'))->invoke($registry);

        self::assertSame([], $submenu);
    }

    #[Test]
    public function registerWithNetworkTrueRemapsOptionsGeneralParentToSettings(): void
    {
        $registry = new AdminPageRegistry();
        $registry->register(new RegistryPositionedNetPage(), network: true);

        // Tracked parent is remapped from options-general.php to settings.php
        // when $network=true, so sortSubmenu looks at $submenu['settings.php'].
        global $submenu;
        $submenu['settings.php'] = [
            ['Net', 'manage_options', 'wppack-netpage'],
        ];

        (new \ReflectionMethod($registry, 'sortSubmenu'))->invoke($registry);

        self::assertSame(['wppack-netpage'], array_column($submenu['settings.php'], 2));
    }
}

#[AsAdminPage(slug: 'registry-test-admin', label: 'Registry Test')]
class RegistryTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '<p>registry test</p>';
    }
}

#[AsAdminPage(slug: 'registry-enqueue-test', label: 'Registry Enqueue Test')]
class RegistryEnqueueTestAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }

    protected function enqueue(): void
    {
        // scripts and styles enqueued
    }
}

#[AsAdminPage(slug: 'registry-auto-scoped', label: 'Registry Auto Scoped', scope: AdminScope::Auto)]
class RegistryAutoScopedAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'registry-network-scoped', label: 'Registry Network Scoped', scope: AdminScope::Network)]
class RegistryNetworkScopedAdminPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'wppack-first', label: 'First', parent: 'options-general.php', position: 100)]
class RegistryPositionedFirstPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'wppack-second', label: 'Second', parent: 'options-general.php', position: 200)]
class RegistryPositionedSecondPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'wppack-third', label: 'Third', parent: 'options-general.php', position: 300)]
class RegistryPositionedThirdPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'wppack-standalone', label: 'Standalone', parent: 'options-general.php', position: 10)]
class RegistryPositionedStandalonePage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}

#[AsAdminPage(slug: 'wppack-netpage', label: 'Net', parent: 'options-general.php', position: 50)]
class RegistryPositionedNetPage extends AbstractAdminPage
{
    public function __invoke(): string
    {
        return '';
    }
}
