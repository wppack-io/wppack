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

namespace WpPack\Component\Admin\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Component\Admin\AdminPageRegistry;
use WpPack\Component\Admin\Attribute\AsAdminPage;
use WpPack\Component\Templating\TemplateRendererInterface;

final class AdminPageRegistryTest extends TestCase
{
    private AdminPageRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new AdminPageRegistry();
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
