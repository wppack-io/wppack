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

namespace WpPack\Component\Hook\Tests\Attribute\User;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\User\Action\DeleteUserAction;
use WpPack\Component\Hook\Attribute\User\Action\DeletedUserAction;
use WpPack\Component\Hook\Attribute\User\Action\EditUserProfileAction;
use WpPack\Component\Hook\Attribute\User\Action\EditUserProfileUpdateAction;
use WpPack\Component\Hook\Attribute\User\Action\PersonalOptionsUpdateAction;
use WpPack\Component\Hook\Attribute\User\Action\ProfileUpdateAction;
use WpPack\Component\Hook\Attribute\User\Action\RemoveUserFromBlogAction;
use WpPack\Component\Hook\Attribute\User\Action\ShowUserProfileAction;
use WpPack\Component\Hook\Attribute\User\Action\UserRegisterAction;
use WpPack\Component\Hook\Attribute\User\Filter\RegistrationErrorsFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function deleteUserActionHasCorrectHookName(): void
    {
        $action = new DeleteUserAction();

        self::assertSame('delete_user', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function deletedUserActionHasCorrectHookName(): void
    {
        $action = new DeletedUserAction();

        self::assertSame('deleted_user', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function editUserProfileActionHasCorrectHookName(): void
    {
        $action = new EditUserProfileAction();

        self::assertSame('edit_user_profile', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function editUserProfileUpdateActionHasCorrectHookName(): void
    {
        $action = new EditUserProfileUpdateAction();

        self::assertSame('edit_user_profile_update', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function personalOptionsUpdateActionHasCorrectHookName(): void
    {
        $action = new PersonalOptionsUpdateAction();

        self::assertSame('personal_options_update', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function profileUpdateActionHasCorrectHookName(): void
    {
        $action = new ProfileUpdateAction();

        self::assertSame('profile_update', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function removeUserFromBlogActionHasCorrectHookName(): void
    {
        $action = new RemoveUserFromBlogAction();

        self::assertSame('remove_user_from_blog', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function showUserProfileActionHasCorrectHookName(): void
    {
        $action = new ShowUserProfileAction();

        self::assertSame('show_user_profile', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function userRegisterActionHasCorrectHookName(): void
    {
        $action = new UserRegisterAction();

        self::assertSame('user_register', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function registrationErrorsFilterHasCorrectHookName(): void
    {
        $filter = new RegistrationErrorsFilter();

        self::assertSame('registration_errors', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function deleteUserActionAcceptsCustomPriority(): void
    {
        $action = new DeleteUserAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new DeleteUserAction());
        self::assertInstanceOf(Action::class, new DeletedUserAction());
        self::assertInstanceOf(Action::class, new EditUserProfileAction());
        self::assertInstanceOf(Action::class, new EditUserProfileUpdateAction());
        self::assertInstanceOf(Action::class, new PersonalOptionsUpdateAction());
        self::assertInstanceOf(Action::class, new ProfileUpdateAction());
        self::assertInstanceOf(Action::class, new RemoveUserFromBlogAction());
        self::assertInstanceOf(Action::class, new ShowUserProfileAction());
        self::assertInstanceOf(Action::class, new UserRegisterAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new RegistrationErrorsFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[UserRegisterAction]
            public function onUserRegister(): void {}

            #[RegistrationErrorsFilter]
            public function onRegistrationErrors(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onUserRegister');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('user_register', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onRegistrationErrors');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('registration_errors', $attributes[0]->newInstance()->hook);
    }
}
