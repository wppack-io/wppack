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

namespace WPPack\Component\Hook\Tests\Attribute\Theme;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\Filter;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;
use WPPack\Component\Hook\Attribute\Theme\Action\CustomizePreviewInitAction;
use WPPack\Component\Hook\Attribute\Theme\Action\CustomizeRegisterAction;
use WPPack\Component\Hook\Attribute\Theme\Action\WpBodyOpenAction;
use WPPack\Component\Hook\Attribute\Theme\Action\WpEnqueueScriptsAction;
use WPPack\Component\Hook\Attribute\Theme\Action\WpFooterAction;
use WPPack\Component\Hook\Attribute\Theme\Action\WpHeadAction;
use WPPack\Component\Hook\Attribute\Theme\Action\WpPrintScriptsAction;
use WPPack\Component\Hook\Attribute\Theme\Action\WpPrintStylesAction;
use WPPack\Component\Hook\Attribute\Theme\Filter\BodyClassFilter;
use WPPack\Component\Hook\Attribute\Theme\Filter\PostClassFilter;
use WPPack\Component\Hook\Attribute\Theme\Filter\ScriptLoaderTagFilter;
use WPPack\Component\Hook\Attribute\Theme\Filter\StyleLoaderTagFilter;

final class NamedHookTest extends TestCase
{
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
    public function customizePreviewInitActionAcceptsCustomPriority(): void
    {
        $action = new CustomizePreviewInitAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CustomizePreviewInitAction());
        self::assertInstanceOf(Action::class, new CustomizeRegisterAction());
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
