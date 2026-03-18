<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Plugin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Plugin\Action\ActivatedPluginAction;
use WpPack\Component\Hook\Attribute\Plugin\Action\AfterPluginRowAction;
use WpPack\Component\Hook\Attribute\Plugin\Action\DeactivatedPluginAction;
use WpPack\Component\Hook\Attribute\Plugin\Action\MuPluginsLoadedAction;
use WpPack\Component\Hook\Attribute\Plugin\Action\NetworkPluginsLoadedAction;
use WpPack\Component\Hook\Attribute\Plugin\Action\PluginLoadedAction;
use WpPack\Component\Hook\Attribute\Plugin\Action\UpgraderProcessCompleteAction;
use WpPack\Component\Hook\Attribute\Plugin\Filter\NetworkPluginActionLinksFilter;
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginActionLinksFilter;
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginRowMetaFilter;
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginsApiFilter;
use WpPack\Component\Hook\Attribute\Plugin\Filter\PreSetSiteTransientUpdatePluginsFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function activatedPluginActionHasCorrectHookName(): void
    {
        $action = new ActivatedPluginAction();

        self::assertSame('activated_plugin', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function afterPluginRowActionHasCorrectHookName(): void
    {
        $action = new AfterPluginRowAction();

        self::assertSame('after_plugin_row', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function deactivatedPluginActionHasCorrectHookName(): void
    {
        $action = new DeactivatedPluginAction();

        self::assertSame('deactivated_plugin', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function muPluginsLoadedActionHasCorrectHookName(): void
    {
        $action = new MuPluginsLoadedAction();

        self::assertSame('muplugins_loaded', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function networkPluginsLoadedActionHasCorrectHookName(): void
    {
        $action = new NetworkPluginsLoadedAction();

        self::assertSame('network_plugins_loaded', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function pluginLoadedActionHasCorrectHookName(): void
    {
        $action = new PluginLoadedAction();

        self::assertSame('plugin_loaded', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function upgraderProcessCompleteActionHasCorrectHookName(): void
    {
        $action = new UpgraderProcessCompleteAction();

        self::assertSame('upgrader_process_complete', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function pluginActionLinksFilterHasCorrectHookName(): void
    {
        $filter = new PluginActionLinksFilter(plugin: 'my-plugin/my-plugin.php');

        self::assertSame('plugin_action_links_my-plugin/my-plugin.php', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
        self::assertSame('my-plugin/my-plugin.php', $filter->plugin);
    }

    #[Test]
    public function networkPluginActionLinksFilterHasCorrectHookName(): void
    {
        $filter = new NetworkPluginActionLinksFilter(plugin: 'my-plugin/my-plugin.php');

        self::assertSame('network_admin_plugin_action_links_my-plugin/my-plugin.php', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame('my-plugin/my-plugin.php', $filter->plugin);
    }

    #[Test]
    public function pluginRowMetaFilterHasCorrectHookName(): void
    {
        $filter = new PluginRowMetaFilter();

        self::assertSame('plugin_row_meta', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function pluginsApiFilterHasCorrectHookName(): void
    {
        $filter = new PluginsApiFilter();

        self::assertSame('plugins_api', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function preSetSiteTransientUpdatePluginsFilterHasCorrectHookName(): void
    {
        $filter = new PreSetSiteTransientUpdatePluginsFilter();

        self::assertSame('pre_set_site_transient_update_plugins', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function activatedPluginActionAcceptsCustomPriority(): void
    {
        $action = new ActivatedPluginAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new ActivatedPluginAction());
        self::assertInstanceOf(Action::class, new AfterPluginRowAction());
        self::assertInstanceOf(Action::class, new DeactivatedPluginAction());
        self::assertInstanceOf(Action::class, new MuPluginsLoadedAction());
        self::assertInstanceOf(Action::class, new NetworkPluginsLoadedAction());
        self::assertInstanceOf(Action::class, new PluginLoadedAction());
        self::assertInstanceOf(Action::class, new UpgraderProcessCompleteAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PluginActionLinksFilter(plugin: 'test/test.php'));
        self::assertInstanceOf(Filter::class, new NetworkPluginActionLinksFilter(plugin: 'test/test.php'));
        self::assertInstanceOf(Filter::class, new PluginRowMetaFilter());
        self::assertInstanceOf(Filter::class, new PluginsApiFilter());
        self::assertInstanceOf(Filter::class, new PreSetSiteTransientUpdatePluginsFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[ActivatedPluginAction]
            public function onActivatedPlugin(): void {}

            #[PluginRowMetaFilter]
            public function onPluginRowMeta(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onActivatedPlugin');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('activated_plugin', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onPluginRowMeta');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('plugin_row_meta', $attributes[0]->newInstance()->hook);
    }

    #[Test]
    public function pluginActionLinksFilterGeneratesDynamicHookName(): void
    {
        $filter1 = new PluginActionLinksFilter(plugin: 'foo/foo.php');
        $filter2 = new PluginActionLinksFilter(plugin: 'bar/bar.php');

        self::assertSame('plugin_action_links_foo/foo.php', $filter1->hook);
        self::assertSame('plugin_action_links_bar/bar.php', $filter2->hook);
    }

    #[Test]
    public function networkPluginActionLinksFilterGeneratesDynamicHookName(): void
    {
        $filter1 = new NetworkPluginActionLinksFilter(plugin: 'foo/foo.php');
        $filter2 = new NetworkPluginActionLinksFilter(plugin: 'bar/bar.php');

        self::assertSame('network_admin_plugin_action_links_foo/foo.php', $filter1->hook);
        self::assertSame('network_admin_plugin_action_links_bar/bar.php', $filter2->hook);
    }
}
