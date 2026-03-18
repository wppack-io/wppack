<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Ajax;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Ajax\Action\CheckAjaxRefererAction;
use WpPack\Component\Hook\Attribute\Ajax\Action\WpAjaxAction;
use WpPack\Component\Hook\Attribute\Ajax\Action\WpAjaxNoprivAction;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function wpAjaxActionHasCorrectHookName(): void
    {
        $action = new WpAjaxAction(action: 'my_action');

        self::assertSame('wp_ajax_my_action', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpAjaxActionAcceptsCustomPriority(): void
    {
        $action = new WpAjaxAction(action: 'my_action', priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function wpAjaxActionPropertyIsAccessible(): void
    {
        $action = new WpAjaxAction(action: 'my_action');

        self::assertSame('my_action', $action->action);
    }

    #[Test]
    public function wpAjaxNoprivActionHasCorrectHookName(): void
    {
        $action = new WpAjaxNoprivAction(action: 'my_action');

        self::assertSame('wp_ajax_nopriv_my_action', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function wpAjaxNoprivActionAcceptsCustomPriority(): void
    {
        $action = new WpAjaxNoprivAction(action: 'my_action', priority: 20);

        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function wpAjaxNoprivActionPropertyIsAccessible(): void
    {
        $action = new WpAjaxNoprivAction(action: 'my_action');

        self::assertSame('my_action', $action->action);
    }

    #[Test]
    public function checkAjaxRefererActionHasCorrectHookName(): void
    {
        $action = new CheckAjaxRefererAction();

        self::assertSame('check_ajax_referer', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function checkAjaxRefererActionAcceptsCustomPriority(): void
    {
        $action = new CheckAjaxRefererAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new WpAjaxAction(action: 'test'));
        self::assertInstanceOf(Action::class, new WpAjaxNoprivAction(action: 'test'));
        self::assertInstanceOf(Action::class, new CheckAjaxRefererAction());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[WpAjaxAction(action: 'my_action')]
            public function onAjax(): void {}

            #[WpAjaxNoprivAction(action: 'public_action', priority: 5)]
            public function onAjaxNopriv(): void {}
        };

        $ajaxMethod = new \ReflectionMethod($class, 'onAjax');
        $attributes = $ajaxMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_ajax_my_action', $attributes[0]->newInstance()->hook);

        $noprivMethod = new \ReflectionMethod($class, 'onAjaxNopriv');
        $attributes = $noprivMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('wp_ajax_nopriv_public_action', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
