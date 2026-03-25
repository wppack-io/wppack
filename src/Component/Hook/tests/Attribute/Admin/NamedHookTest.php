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

namespace WpPack\Component\Hook\Tests\Attribute\Admin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminBarMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminEnqueueScriptsAction;
use WpPack\Component\Hook\Attribute\Admin\Action\CheckAdminRefererAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminFooterAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminHeadAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminNoticesAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminPrintFooterScriptsAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminPrintScriptsAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminPrintStylesAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AllAdminNoticesAction;
use WpPack\Component\Hook\Attribute\Admin\Action\CurrentScreenAction;
use WpPack\Component\Hook\Attribute\Admin\Action\ManagePostsCustomColumnAction;
use WpPack\Component\Hook\Attribute\Admin\Action\NetworkAdminMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\NetworkAdminNoticesAction;
use WpPack\Component\Hook\Attribute\Admin\Action\UserAdminMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\UserAdminNoticesAction;
use WpPack\Component\Hook\Attribute\Admin\Action\WpBeforeAdminBarRenderAction;
use WpPack\Component\Hook\Attribute\Admin\Filter\AdminBodyClassFilter;
use WpPack\Component\Hook\Attribute\Admin\Filter\AdminFooterTextFilter;
use WpPack\Component\Hook\Attribute\Admin\Filter\AdminTitleFilter;
use WpPack\Component\Hook\Attribute\Admin\Filter\ManagePagesColumnsFilter;
use WpPack\Component\Hook\Attribute\Admin\Filter\ManagePostsColumnsFilter;
use WpPack\Component\Hook\Attribute\Admin\Filter\ManageUsersColumnsFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function checkAdminRefererActionHasCorrectHookName(): void
    {
        $action = new CheckAdminRefererAction();

        self::assertSame('check_admin_referer', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function checkAdminRefererActionAcceptsCustomPriority(): void
    {
        $action = new CheckAdminRefererAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

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
    public function adminEnqueueScriptsActionAcceptsCustomPriority(): void
    {
        $action = new AdminEnqueueScriptsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminFooterActionHasCorrectHookName(): void
    {
        $action = new AdminFooterAction();

        self::assertSame('admin_footer', $action->hook);
    }

    #[Test]
    public function adminFooterActionAcceptsCustomPriority(): void
    {
        $action = new AdminFooterAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminHeadActionHasCorrectHookName(): void
    {
        $action = new AdminHeadAction();

        self::assertSame('admin_head', $action->hook);
    }

    #[Test]
    public function adminHeadActionAcceptsCustomPriority(): void
    {
        $action = new AdminHeadAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminMenuActionHasCorrectHookName(): void
    {
        $action = new AdminMenuAction();

        self::assertSame('admin_menu', $action->hook);
    }

    #[Test]
    public function adminMenuActionAcceptsCustomPriority(): void
    {
        $action = new AdminMenuAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminNoticesActionHasCorrectHookName(): void
    {
        $action = new AdminNoticesAction();

        self::assertSame('admin_notices', $action->hook);
    }

    #[Test]
    public function adminNoticesActionAcceptsCustomPriority(): void
    {
        $action = new AdminNoticesAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminPrintFooterScriptsActionHasCorrectHookName(): void
    {
        $action = new AdminPrintFooterScriptsAction();

        self::assertSame('admin_print_footer_scripts', $action->hook);
    }

    #[Test]
    public function adminPrintFooterScriptsActionAcceptsCustomPriority(): void
    {
        $action = new AdminPrintFooterScriptsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminPrintScriptsActionHasCorrectHookName(): void
    {
        $action = new AdminPrintScriptsAction();

        self::assertSame('admin_print_scripts', $action->hook);
    }

    #[Test]
    public function adminPrintScriptsActionAcceptsCustomPriority(): void
    {
        $action = new AdminPrintScriptsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminPrintStylesActionHasCorrectHookName(): void
    {
        $action = new AdminPrintStylesAction();

        self::assertSame('admin_print_styles', $action->hook);
    }

    #[Test]
    public function adminPrintStylesActionAcceptsCustomPriority(): void
    {
        $action = new AdminPrintStylesAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allAdminNoticesActionHasCorrectHookName(): void
    {
        $action = new AllAdminNoticesAction();

        self::assertSame('all_admin_notices', $action->hook);
    }

    #[Test]
    public function allAdminNoticesActionAcceptsCustomPriority(): void
    {
        $action = new AllAdminNoticesAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function currentScreenActionHasCorrectHookName(): void
    {
        $action = new CurrentScreenAction();

        self::assertSame('current_screen', $action->hook);
    }

    #[Test]
    public function currentScreenActionAcceptsCustomPriority(): void
    {
        $action = new CurrentScreenAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function managePostsCustomColumnActionHasCorrectHookName(): void
    {
        $action = new ManagePostsCustomColumnAction();

        self::assertSame('manage_posts_custom_column', $action->hook);
    }

    #[Test]
    public function managePostsCustomColumnActionAcceptsCustomPriority(): void
    {
        $action = new ManagePostsCustomColumnAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function networkAdminMenuActionHasCorrectHookName(): void
    {
        $action = new NetworkAdminMenuAction();

        self::assertSame('network_admin_menu', $action->hook);
    }

    #[Test]
    public function networkAdminMenuActionAcceptsCustomPriority(): void
    {
        $action = new NetworkAdminMenuAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function networkAdminNoticesActionHasCorrectHookName(): void
    {
        $action = new NetworkAdminNoticesAction();

        self::assertSame('network_admin_notices', $action->hook);
    }

    #[Test]
    public function networkAdminNoticesActionAcceptsCustomPriority(): void
    {
        $action = new NetworkAdminNoticesAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function userAdminMenuActionHasCorrectHookName(): void
    {
        $action = new UserAdminMenuAction();

        self::assertSame('user_admin_menu', $action->hook);
    }

    #[Test]
    public function userAdminMenuActionAcceptsCustomPriority(): void
    {
        $action = new UserAdminMenuAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function userAdminNoticesActionHasCorrectHookName(): void
    {
        $action = new UserAdminNoticesAction();

        self::assertSame('user_admin_notices', $action->hook);
    }

    #[Test]
    public function userAdminNoticesActionAcceptsCustomPriority(): void
    {
        $action = new UserAdminNoticesAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function wpBeforeAdminBarRenderActionHasCorrectHookName(): void
    {
        $action = new WpBeforeAdminBarRenderAction();

        self::assertSame('wp_before_admin_bar_render', $action->hook);
    }

    #[Test]
    public function wpBeforeAdminBarRenderActionAcceptsCustomPriority(): void
    {
        $action = new WpBeforeAdminBarRenderAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function managePagesColumnsFilterHasCorrectHookName(): void
    {
        $filter = new ManagePagesColumnsFilter();

        self::assertSame('manage_pages_columns', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function managePagesColumnsFilterAcceptsCustomPriority(): void
    {
        $filter = new ManagePagesColumnsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function managePostsColumnsFilterHasCorrectHookName(): void
    {
        $filter = new ManagePostsColumnsFilter();

        self::assertSame('manage_posts_columns', $filter->hook);
    }

    #[Test]
    public function managePostsColumnsFilterAcceptsCustomPriority(): void
    {
        $filter = new ManagePostsColumnsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function manageUsersColumnsFilterHasCorrectHookName(): void
    {
        $filter = new ManageUsersColumnsFilter();

        self::assertSame('manage_users_columns', $filter->hook);
    }

    #[Test]
    public function manageUsersColumnsFilterAcceptsCustomPriority(): void
    {
        $filter = new ManageUsersColumnsFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function adminBodyClassFilterHasCorrectHookName(): void
    {
        $filter = new AdminBodyClassFilter();

        self::assertSame('admin_body_class', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function adminBodyClassFilterAcceptsCustomPriority(): void
    {
        $filter = new AdminBodyClassFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function adminFooterTextFilterHasCorrectHookName(): void
    {
        $filter = new AdminFooterTextFilter();

        self::assertSame('admin_footer_text', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function adminFooterTextFilterAcceptsCustomPriority(): void
    {
        $filter = new AdminFooterTextFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function adminTitleFilterHasCorrectHookName(): void
    {
        $filter = new AdminTitleFilter();

        self::assertSame('admin_title', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function adminTitleFilterAcceptsCustomPriority(): void
    {
        $filter = new AdminTitleFilter(priority: 5);

        self::assertSame(5, $filter->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CheckAdminRefererAction());
        self::assertInstanceOf(Action::class, new AdminBarMenuAction());
        self::assertInstanceOf(Action::class, new AdminEnqueueScriptsAction());
        self::assertInstanceOf(Action::class, new AdminFooterAction());
        self::assertInstanceOf(Action::class, new AdminHeadAction());
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
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new AdminBodyClassFilter());
        self::assertInstanceOf(Filter::class, new AdminFooterTextFilter());
        self::assertInstanceOf(Filter::class, new AdminTitleFilter());
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

            #[AdminBodyClassFilter]
            public function onBodyClass(): void {}
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

        $bodyClassMethod = new \ReflectionMethod($class, 'onBodyClass');
        $attributes = $bodyClassMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('admin_body_class', $attributes[0]->newInstance()->hook);
    }
}
