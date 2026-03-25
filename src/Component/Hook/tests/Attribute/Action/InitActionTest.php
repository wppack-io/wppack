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

namespace WpPack\Component\Hook\Tests\Attribute\Action;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Action\AdminInitAction;
use WpPack\Component\Hook\Attribute\Action\AfterSetupThemeAction;
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\Action\PluginsLoadedAction;
use WpPack\Component\Hook\Attribute\Action\WpLoadedAction;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;

final class InitActionTest extends TestCase
{
    #[Test]
    public function initActionHasCorrectHookName(): void
    {
        $action = new InitAction();

        self::assertSame('init', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function initActionAcceptsCustomPriority(): void
    {
        $action = new InitAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function adminInitActionHasCorrectHookName(): void
    {
        $action = new AdminInitAction();

        self::assertSame('admin_init', $action->hook);
    }

    #[Test]
    public function pluginsLoadedActionHasCorrectHookName(): void
    {
        $action = new PluginsLoadedAction();

        self::assertSame('plugins_loaded', $action->hook);
    }

    #[Test]
    public function afterSetupThemeActionHasCorrectHookName(): void
    {
        $action = new AfterSetupThemeAction();

        self::assertSame('after_setup_theme', $action->hook);
    }

    #[Test]
    public function wpLoadedActionHasCorrectHookName(): void
    {
        $action = new WpLoadedAction();

        self::assertSame('wp_loaded', $action->hook);
    }

    #[Test]
    public function namedActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new InitAction());
        self::assertInstanceOf(Action::class, new AdminInitAction());
        self::assertInstanceOf(Action::class, new PluginsLoadedAction());
        self::assertInstanceOf(Action::class, new AfterSetupThemeAction());
        self::assertInstanceOf(Action::class, new WpLoadedAction());
    }

    #[Test]
    public function namedActionsAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[InitAction]
            public function onInit(): void {}

            #[AdminInitAction(priority: 5)]
            public function onAdminInit(): void {}
        };

        $initMethod = new \ReflectionMethod($class, 'onInit');
        $attributes = $initMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('init', $attributes[0]->newInstance()->hook);

        $adminMethod = new \ReflectionMethod($class, 'onAdminInit');
        $attributes = $adminMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('admin_init', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
