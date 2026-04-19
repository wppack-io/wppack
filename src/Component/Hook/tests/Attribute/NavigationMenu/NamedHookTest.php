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

namespace WPPack\Component\Hook\Tests\Attribute\NavigationMenu;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;
use WPPack\Component\Hook\Attribute\NavigationMenu\Action\WpCreateNavMenuAction;
use WPPack\Component\Hook\Attribute\NavigationMenu\Action\WpDeleteNavMenuAction;
use WPPack\Component\Hook\Attribute\NavigationMenu\Action\WpNavMenuItemCustomFieldsAction;
use WPPack\Component\Hook\Attribute\NavigationMenu\Action\WpUpdateNavMenuAction;
use WPPack\Component\Hook\Attribute\NavigationMenu\Action\WpUpdateNavMenuItemAction;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\NavMenuCssClassFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\PreWpNavMenuFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\NavMenuItemIdFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\NavMenuLinkAttributesFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\WpNavMenuArgsFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\WpNavMenuItemsFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\WpNavMenuObjectsFilter;
use WPPack\Component\Hook\Attribute\NavigationMenu\Filter\WpSetupNavMenuItemFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpNavMenuItemCustomFieldsActionHasCorrectHookName(): void
    {
        $action = new WpNavMenuItemCustomFieldsAction();

        self::assertSame('wp_nav_menu_item_custom_fields', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpUpdateNavMenuItemActionHasCorrectHookName(): void
    {
        $action = new WpUpdateNavMenuItemAction();

        self::assertSame('wp_update_nav_menu_item', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function navMenuCssClassFilterHasCorrectHookName(): void
    {
        $filter = new NavMenuCssClassFilter();

        self::assertSame('nav_menu_css_class', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function preWpNavMenuFilterHasCorrectHookName(): void
    {
        $filter = new PreWpNavMenuFilter();

        self::assertSame('pre_wp_nav_menu', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function wpNavMenuArgsFilterHasCorrectHookName(): void
    {
        $filter = new WpNavMenuArgsFilter();

        self::assertSame('wp_nav_menu_args', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function wpNavMenuItemsFilterHasCorrectHookName(): void
    {
        $filter = new WpNavMenuItemsFilter();

        self::assertSame('wp_nav_menu_items', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function wpNavMenuObjectsFilterHasCorrectHookName(): void
    {
        $filter = new WpNavMenuObjectsFilter();

        self::assertSame('wp_nav_menu_objects', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function wpNavMenuItemCustomFieldsActionAcceptsCustomPriority(): void
    {
        $action = new WpNavMenuItemCustomFieldsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function wpSetupNavMenuItemFilterHasCorrectHookName(): void
    {
        $filter = new WpSetupNavMenuItemFilter();

        self::assertSame('wp_setup_nav_menu_item', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function navMenuItemIdFilterHasCorrectHookName(): void
    {
        $filter = new NavMenuItemIdFilter();

        self::assertSame('nav_menu_item_id', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function navMenuLinkAttributesFilterHasCorrectHookName(): void
    {
        $filter = new NavMenuLinkAttributesFilter();

        self::assertSame('nav_menu_link_attributes', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function wpCreateNavMenuActionHasCorrectHookName(): void
    {
        $action = new WpCreateNavMenuAction();

        self::assertSame('wp_create_nav_menu', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpUpdateNavMenuActionHasCorrectHookName(): void
    {
        $action = new WpUpdateNavMenuAction();

        self::assertSame('wp_update_nav_menu', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpDeleteNavMenuActionHasCorrectHookName(): void
    {
        $action = new WpDeleteNavMenuAction();

        self::assertSame('wp_delete_nav_menu', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpNavMenuItemCustomFieldsAction());
        self::assertInstanceOf(Action::class, new WpUpdateNavMenuItemAction());
        self::assertInstanceOf(Action::class, new WpCreateNavMenuAction());
        self::assertInstanceOf(Action::class, new WpUpdateNavMenuAction());
        self::assertInstanceOf(Action::class, new WpDeleteNavMenuAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new NavMenuCssClassFilter());
        self::assertInstanceOf(Filter::class, new PreWpNavMenuFilter());
        self::assertInstanceOf(Filter::class, new WpNavMenuArgsFilter());
        self::assertInstanceOf(Filter::class, new WpNavMenuItemsFilter());
        self::assertInstanceOf(Filter::class, new WpNavMenuObjectsFilter());
        self::assertInstanceOf(Filter::class, new WpSetupNavMenuItemFilter());
        self::assertInstanceOf(Filter::class, new NavMenuItemIdFilter());
        self::assertInstanceOf(Filter::class, new NavMenuLinkAttributesFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpUpdateNavMenuItemAction]
            public function onUpdateNavMenuItem(): void {}

            #[NavMenuCssClassFilter]
            public function onNavMenuCssClass(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onUpdateNavMenuItem');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_update_nav_menu_item', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onNavMenuCssClass');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('nav_menu_css_class', $attributes[0]->newInstance()->hook);
    }
}
