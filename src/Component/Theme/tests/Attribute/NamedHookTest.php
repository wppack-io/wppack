<?php

declare(strict_types=1);

namespace WpPack\Component\Theme\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Theme\Attribute\Action\AfterSetupThemeAction;
use WpPack\Component\Theme\Attribute\Action\CustomizePreviewInitAction;
use WpPack\Component\Theme\Attribute\Action\CustomizeRegisterAction;
use WpPack\Component\Theme\Attribute\Action\TemplateRedirectAction;
use WpPack\Component\Theme\Attribute\Action\WpBodyOpenAction;
use WpPack\Component\Theme\Attribute\Action\WpEnqueueScriptsAction;
use WpPack\Component\Theme\Attribute\Action\WpFooterAction;
use WpPack\Component\Theme\Attribute\Action\WpHeadAction;
use WpPack\Component\Theme\Attribute\Action\WpPrintScriptsAction;
use WpPack\Component\Theme\Attribute\Action\WpPrintStylesAction;
use WpPack\Component\Theme\Attribute\Filter\BodyClassFilter;
use WpPack\Component\Theme\Attribute\Filter\PostClassFilter;
use WpPack\Component\Theme\Attribute\Filter\ScriptLoaderTagFilter;
use WpPack\Component\Theme\Attribute\Filter\StyleLoaderTagFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function afterSetupThemeActionHasCorrectHookName(): void
    {
        $action = new AfterSetupThemeAction();

        self::assertSame('after_setup_theme', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function customizePreviewInitActionHasCorrectHookName(): void
    {
        $action = new CustomizePreviewInitAction();

        self::assertSame('customize_preview_init', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function customizeRegisterActionHasCorrectHookName(): void
    {
        $action = new CustomizeRegisterAction();

        self::assertSame('customize_register', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function templateRedirectActionHasCorrectHookName(): void
    {
        $action = new TemplateRedirectAction();

        self::assertSame('template_redirect', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpBodyOpenActionHasCorrectHookName(): void
    {
        $action = new WpBodyOpenAction();

        self::assertSame('wp_body_open', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpEnqueueScriptsActionHasCorrectHookName(): void
    {
        $action = new WpEnqueueScriptsAction();

        self::assertSame('wp_enqueue_scripts', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpFooterActionHasCorrectHookName(): void
    {
        $action = new WpFooterAction();

        self::assertSame('wp_footer', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpHeadActionHasCorrectHookName(): void
    {
        $action = new WpHeadAction();

        self::assertSame('wp_head', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpPrintScriptsActionHasCorrectHookName(): void
    {
        $action = new WpPrintScriptsAction();

        self::assertSame('wp_print_scripts', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpPrintStylesActionHasCorrectHookName(): void
    {
        $action = new WpPrintStylesAction();

        self::assertSame('wp_print_styles', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function bodyClassFilterHasCorrectHookName(): void
    {
        $filter = new BodyClassFilter();

        self::assertSame('body_class', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function postClassFilterHasCorrectHookName(): void
    {
        $filter = new PostClassFilter();

        self::assertSame('post_class', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function scriptLoaderTagFilterHasCorrectHookName(): void
    {
        $filter = new ScriptLoaderTagFilter();

        self::assertSame('script_loader_tag', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function styleLoaderTagFilterHasCorrectHookName(): void
    {
        $filter = new StyleLoaderTagFilter();

        self::assertSame('style_loader_tag', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
    }

    #[Test]
    public function afterSetupThemeActionAcceptsCustomPriority(): void
    {
        $action = new AfterSetupThemeAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new AfterSetupThemeAction());
        self::assertInstanceOf(Action::class, new CustomizePreviewInitAction());
        self::assertInstanceOf(Action::class, new CustomizeRegisterAction());
        self::assertInstanceOf(Action::class, new TemplateRedirectAction());
        self::assertInstanceOf(Action::class, new WpBodyOpenAction());
        self::assertInstanceOf(Action::class, new WpEnqueueScriptsAction());
        self::assertInstanceOf(Action::class, new WpFooterAction());
        self::assertInstanceOf(Action::class, new WpHeadAction());
        self::assertInstanceOf(Action::class, new WpPrintScriptsAction());
        self::assertInstanceOf(Action::class, new WpPrintStylesAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new BodyClassFilter());
        self::assertInstanceOf(Filter::class, new PostClassFilter());
        self::assertInstanceOf(Filter::class, new ScriptLoaderTagFilter());
        self::assertInstanceOf(Filter::class, new StyleLoaderTagFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpEnqueueScriptsAction]
            public function onEnqueueScripts(): void {}

            #[BodyClassFilter]
            public function onBodyClass(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onEnqueueScripts');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_enqueue_scripts', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onBodyClass');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('body_class', $attributes[0]->newInstance()->hook);
    }
}
