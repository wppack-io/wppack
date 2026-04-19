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

namespace WPPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\AdminDataCollector;

final class AdminDataCollectorTest extends TestCase
{
    private AdminDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new AdminDataCollector();
    }

    #[Test]
    public function getNameReturnsAdmin(): void
    {
        self::assertSame('admin', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsAdmin(): void
    {
        self::assertSame('Admin', $this->collector->getLabel());
    }

    #[Test]
    public function getIndicatorValueReturnsScreenIdWhenAdmin(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_admin' => true,
            'screen' => ['id' => 'edit-post'],
            'page_hook' => 'edit.php',
        ]);

        self::assertSame('edit-post', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNotAdmin(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_admin' => false,
        ]);

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsDefault(): void
    {
        self::assertSame('default', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, ['is_admin' => true]);
        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());
    }

    #[Test]
    public function collectWithAdminScreenReturnsScreenData(): void
    {

        $savedMenu = $GLOBALS['menu'] ?? null;
        $savedSubmenu = $GLOBALS['submenu'] ?? null;
        $savedPagenow = $GLOBALS['pagenow'] ?? null;

        set_current_screen('edit-post');
        $GLOBALS['pagenow'] = 'edit.php';
        $GLOBALS['menu'] = [
            ['Posts', 'edit_posts', 'edit.php', '', 'menu-top', 'menu-posts', 'dashicons-admin-post'],
        ];
        $GLOBALS['submenu'] = [
            'edit.php' => [
                ['All Posts', 'edit_posts', 'edit.php'],
                ['Add New', 'edit_posts', 'post-new.php'],
            ],
        ];

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertTrue($data['is_admin']);
            self::assertSame('edit.php', $data['page_hook']);
            self::assertNotEmpty($data['screen']);
            self::assertSame('edit-post', $data['screen']['id']);
            self::assertSame('edit', $data['screen']['base']);
            self::assertSame('post', $data['screen']['post_type']);
            self::assertSame(1, $data['total_menus']);
            self::assertSame(2, $data['total_submenus']);
            self::assertSame('Posts', $data['admin_menus'][0]['title']);
            self::assertSame('edit.php', $data['admin_menus'][0]['slug']);
            self::assertSame('edit_posts', $data['admin_menus'][0]['capability']);
            self::assertCount(2, $data['admin_menus'][0]['submenu']);
        } finally {
            if ($savedMenu !== null) {
                $GLOBALS['menu'] = $savedMenu;
            } else {
                unset($GLOBALS['menu']);
            }
            if ($savedSubmenu !== null) {
                $GLOBALS['submenu'] = $savedSubmenu;
            } else {
                unset($GLOBALS['submenu']);
            }
            if ($savedPagenow !== null) {
                $GLOBALS['pagenow'] = $savedPagenow;
            }
            set_current_screen('front');
        }
    }

    #[Test]
    public function collectSkipsEmptyMenuItems(): void
    {

        $savedMenu = $GLOBALS['menu'] ?? null;
        $savedSubmenu = $GLOBALS['submenu'] ?? null;

        set_current_screen('dashboard');
        $GLOBALS['menu'] = [
            ['Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', 'dashicons-dashboard'],
            ['', '', '', '', 'wp-menu-separator'],
        ];
        $GLOBALS['submenu'] = [];

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertTrue($data['is_admin']);
            self::assertSame(1, $data['total_menus']);
        } finally {
            if ($savedMenu !== null) {
                $GLOBALS['menu'] = $savedMenu;
            } else {
                unset($GLOBALS['menu']);
            }
            if ($savedSubmenu !== null) {
                $GLOBALS['submenu'] = $savedSubmenu;
            } else {
                unset($GLOBALS['submenu']);
            }
            set_current_screen('front');
        }
    }

    #[Test]
    public function collectWithAdminBarReturnsTopLevelNodes(): void
    {
        $savedAdminBar = $GLOBALS['wp_admin_bar'] ?? null;

        set_current_screen('dashboard');
        $GLOBALS['menu'] = [];
        $GLOBALS['submenu'] = [];

        $adminBar = new \WP_Admin_Bar();
        $adminBar->add_node([
            'id' => 'site-name',
            'title' => 'My Site',
            'parent' => '',
        ]);
        $adminBar->add_node([
            'id' => 'child-node',
            'title' => 'Child',
            'parent' => 'site-name',
        ]);
        $GLOBALS['wp_admin_bar'] = $adminBar;

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertTrue($data['is_admin']);
            // Only top-level nodes (parent === '') should be included
            $topLevelIds = array_column($data['admin_bar_nodes'], 'id');
            self::assertContains('site-name', $topLevelIds);
            self::assertNotContains('child-node', $topLevelIds);
        } finally {
            if ($savedAdminBar !== null) {
                $GLOBALS['wp_admin_bar'] = $savedAdminBar;
            } else {
                unset($GLOBALS['wp_admin_bar']);
            }
            unset($GLOBALS['menu'], $GLOBALS['submenu']);
            set_current_screen('front');
        }
    }

    #[Test]
    public function collectOutsideAdminReturnsNonAdminDefaults(): void
    {

        set_current_screen('front');

        try {
            $this->collector->collect();
            $data = $this->collector->getData();

            self::assertFalse($data['is_admin']);
            self::assertSame([], $data['admin_menus']);
            self::assertSame([], $data['admin_bar_nodes']);
            self::assertSame(0, $data['total_menus']);
            self::assertSame(0, $data['total_submenus']);
        } finally {
            set_current_screen('front');
        }
    }

    #[Test]
    public function getIndicatorValueFallsBackToPageHookWhenNoScreenId(): void
    {
        $reflection = new \ReflectionProperty($this->collector, 'data');
        $reflection->setValue($this->collector, [
            'is_admin' => true,
            'screen' => [],
            'page_hook' => 'options-general.php',
        ]);

        self::assertSame('options-general.php', $this->collector->getIndicatorValue());
    }
}
