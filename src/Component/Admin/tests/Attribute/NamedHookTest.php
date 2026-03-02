<?php

declare(strict_types=1);

namespace WpPack\Component\Admin\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Admin\Attribute\Action\AdminBarMenuAction;
use WpPack\Component\Admin\Attribute\Action\AdminEnqueueScriptsAction;
use WpPack\Component\Admin\Attribute\Action\AdminFooterAction;
use WpPack\Component\Admin\Attribute\Action\AdminHeadAction;
use WpPack\Component\Admin\Attribute\Action\AdminInitAction;
use WpPack\Component\Admin\Attribute\Action\AdminMenuAction;
use WpPack\Component\Admin\Attribute\Action\AdminNoticesAction;
use WpPack\Component\Admin\Attribute\Action\AdminPrintFooterScriptsAction;
use WpPack\Component\Admin\Attribute\Action\AdminPrintScriptsAction;
use WpPack\Component\Admin\Attribute\Action\AdminPrintStylesAction;
use WpPack\Component\Admin\Attribute\Action\AllAdminNoticesAction;
use WpPack\Component\Admin\Attribute\Action\CurrentScreenAction;
use WpPack\Component\Admin\Attribute\Action\ManagePostsCustomColumnAction;
use WpPack\Component\Admin\Attribute\Action\NetworkAdminMenuAction;
use WpPack\Component\Admin\Attribute\Action\NetworkAdminNoticesAction;
use WpPack\Component\Admin\Attribute\Action\UserAdminMenuAction;
use WpPack\Component\Admin\Attribute\Action\UserAdminNoticesAction;
use WpPack\Component\Admin\Attribute\Action\WpBeforeAdminBarRenderAction;
use WpPack\Component\Admin\Attribute\Action\WpDashboardSetupAction;
use WpPack\Component\Admin\Attribute\Action\WpNetworkDashboardSetupAction;
use WpPack\Component\Admin\Attribute\Filter\ManagePagesColumnsFilter;
use WpPack\Component\Admin\Attribute\Filter\ManagePostsColumnsFilter;
use WpPack\Component\Admin\Attribute\Filter\ManageUsersColumnsFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function adminBarMenuActionHasCorrectHookName(): void
    {
        $action = new AdminBarMenuAction();

        self::assertSame('admin_bar_menu', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function adminBarMenuActionAcceptsCustomPriority(): void
    {
        $action = new AdminBarMenuAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminEnqueueScriptsActionHasCorrectHookName(): void
    {
        $action = new AdminEnqueueScriptsAction();

        self::assertSame('admin_enqueue_scripts', $action->hook);
    }

    #[Test]
    public function adminFooterActionHasCorrectHookName(): void
    {
        $action = new AdminFooterAction();

        self::assertSame('admin_footer', $action->hook);
    }

    #[Test]
    public function adminHeadActionHasCorrectHookName(): void
    {
        $action = new AdminHeadAction();

        self::assertSame('admin_head', $action->hook);
    }

    #[Test]
    public function adminInitActionHasCorrectHookName(): void
    {
        $action = new AdminInitAction();

        self::assertSame('admin_init', $action->hook);
    }

    #[Test]
    public function adminMenuActionHasCorrectHookName(): void
    {
        $action = new AdminMenuAction();

        self::assertSame('admin_menu', $action->hook);
    }

    #[Test]
    public function adminNoticesActionHasCorrectHookName(): void
    {
        $action = new AdminNoticesAction();

        self::assertSame('admin_notices', $action->hook);
    }

    #[Test]
    public function adminPrintFooterScriptsActionHasCorrectHookName(): void
    {
        $action = new AdminPrintFooterScriptsAction();

        self::assertSame('admin_print_footer_scripts', $action->hook);
    }

    #[Test]
    public function adminPrintScriptsActionHasCorrectHookName(): void
    {
        $action = new AdminPrintScriptsAction();

        self::assertSame('admin_print_scripts', $action->hook);
    }

    #[Test]
    public function adminPrintStylesActionHasCorrectHookName(): void
    {
        $action = new AdminPrintStylesAction();

        self::assertSame('admin_print_styles', $action->hook);
    }

    #[Test]
    public function allAdminNoticesActionHasCorrectHookName(): void
    {
        $action = new AllAdminNoticesAction();

        self::assertSame('all_admin_notices', $action->hook);
    }

    #[Test]
    public function currentScreenActionHasCorrectHookName(): void
    {
        $action = new CurrentScreenAction();

        self::assertSame('current_screen', $action->hook);
    }

    #[Test]
    public function managePostsCustomColumnActionHasCorrectHookName(): void
    {
        $action = new ManagePostsCustomColumnAction();

        self::assertSame('manage_posts_custom_column', $action->hook);
    }

    #[Test]
    public function networkAdminMenuActionHasCorrectHookName(): void
    {
        $action = new NetworkAdminMenuAction();

        self::assertSame('network_admin_menu', $action->hook);
    }

    #[Test]
    public function networkAdminNoticesActionHasCorrectHookName(): void
    {
        $action = new NetworkAdminNoticesAction();

        self::assertSame('network_admin_notices', $action->hook);
    }

    #[Test]
    public function userAdminMenuActionHasCorrectHookName(): void
    {
        $action = new UserAdminMenuAction();

        self::assertSame('user_admin_menu', $action->hook);
    }

    #[Test]
    public function userAdminNoticesActionHasCorrectHookName(): void
    {
        $action = new UserAdminNoticesAction();

        self::assertSame('user_admin_notices', $action->hook);
    }

    #[Test]
    public function wpBeforeAdminBarRenderActionHasCorrectHookName(): void
    {
        $action = new WpBeforeAdminBarRenderAction();

        self::assertSame('wp_before_admin_bar_render', $action->hook);
    }

    #[Test]
    public function wpDashboardSetupActionHasCorrectHookName(): void
    {
        $action = new WpDashboardSetupAction();

        self::assertSame('wp_dashboard_setup', $action->hook);
    }

    #[Test]
    public function wpNetworkDashboardSetupActionHasCorrectHookName(): void
    {
        $action = new WpNetworkDashboardSetupAction();

        self::assertSame('wp_network_dashboard_setup', $action->hook);
    }

    #[Test]
    public function managePagesColumnsFilterHasCorrectHookName(): void
    {
        $filter = new ManagePagesColumnsFilter();

        self::assertSame('manage_pages_columns', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function managePostsColumnsFilterHasCorrectHookName(): void
    {
        $filter = new ManagePostsColumnsFilter();

        self::assertSame('manage_posts_columns', $filter->hook);
    }

    #[Test]
    public function manageUsersColumnsFilterHasCorrectHookName(): void
    {
        $filter = new ManageUsersColumnsFilter();

        self::assertSame('manage_users_columns', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new AdminBarMenuAction());
        self::assertInstanceOf(Action::class, new AdminEnqueueScriptsAction());
        self::assertInstanceOf(Action::class, new AdminFooterAction());
        self::assertInstanceOf(Action::class, new AdminHeadAction());
        self::assertInstanceOf(Action::class, new AdminInitAction());
        self::assertInstanceOf(Action::class, new AdminMenuAction());
        self::assertInstanceOf(Action::class, new AdminNoticesAction());
        self::assertInstanceOf(Action::class, new AdminPrintFooterScriptsAction());
        self::assertInstanceOf(Action::class, new AdminPrintScriptsAction());
        self::assertInstanceOf(Action::class, new AdminPrintStylesAction());
        self::assertInstanceOf(Action::class, new AllAdminNoticesAction());
        self::assertInstanceOf(Action::class, new CurrentScreenAction());
        self::assertInstanceOf(Action::class, new ManagePostsCustomColumnAction());
        self::assertInstanceOf(Action::class, new NetworkAdminMenuAction());
        self::assertInstanceOf(Action::class, new NetworkAdminNoticesAction());
        self::assertInstanceOf(Action::class, new UserAdminMenuAction());
        self::assertInstanceOf(Action::class, new UserAdminNoticesAction());
        self::assertInstanceOf(Action::class, new WpBeforeAdminBarRenderAction());
        self::assertInstanceOf(Action::class, new WpDashboardSetupAction());
        self::assertInstanceOf(Action::class, new WpNetworkDashboardSetupAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new ManagePagesColumnsFilter());
        self::assertInstanceOf(Filter::class, new ManagePostsColumnsFilter());
        self::assertInstanceOf(Filter::class, new ManageUsersColumnsFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[AdminMenuAction]
            public function onAdminMenu(): void {}

            #[ManagePostsColumnsFilter(priority: 5)]
            public function onColumns(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onAdminMenu');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('admin_menu', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onColumns');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('manage_posts_columns', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
