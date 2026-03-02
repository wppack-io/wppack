<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Setting\Attribute\Action\SettingsErrorsAction;
use WpPack\Component\Setting\Attribute\Action\SettingsPageAction;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function settingsErrorsActionHasCorrectHookName(): void
    {
        $action = new SettingsErrorsAction();

        self::assertSame('settings_errors', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function settingsPageActionHasCorrectHookName(): void
    {
        $action = new SettingsPageAction(page: 'general');

        self::assertSame('settings_page_general', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
        self::assertSame('general', $action->page);
    }

    #[Test]
    public function settingsErrorsActionAcceptsCustomPriority(): void
    {
        $action = new SettingsErrorsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function settingsPageActionGeneratesDynamicHookName(): void
    {
        $action1 = new SettingsPageAction(page: 'general');
        $action2 = new SettingsPageAction(page: 'reading');

        self::assertSame('settings_page_general', $action1->hook);
        self::assertSame('settings_page_reading', $action2->hook);
    }

    #[Test]
    public function settingsPageActionPagePropertyIsAccessible(): void
    {
        $action = new SettingsPageAction(page: 'writing');

        self::assertSame('writing', $action->page);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new SettingsErrorsAction());
        self::assertInstanceOf(Action::class, new SettingsPageAction(page: 'general'));
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[SettingsErrorsAction]
            public function onSettingsErrors(): void {}

            #[SettingsPageAction(page: 'general')]
            public function onSettingsPage(): void {}
        };

        $errorsMethod = new \ReflectionMethod($class, 'onSettingsErrors');
        $attributes = $errorsMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('settings_errors', $attributes[0]->newInstance()->hook);

        $pageMethod = new \ReflectionMethod($class, 'onSettingsPage');
        $attributes = $pageMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('settings_page_general', $attributes[0]->newInstance()->hook);
    }
}
