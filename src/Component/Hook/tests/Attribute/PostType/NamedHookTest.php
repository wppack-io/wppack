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

namespace WPPack\Component\Hook\Tests\Attribute\PostType;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Hook\Attribute\Action;
use WPPack\Component\Hook\Attribute\PostType\Action\DeletePostAction;
use WPPack\Component\Hook\Attribute\PostType\Action\SavePostAction;
use WPPack\Component\Hook\Attribute\PostType\Action\TransitionPostStatusAction;
use WPPack\Component\Hook\Hook;
use WPPack\Component\Hook\HookType;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function deletePostActionHasCorrectHookName(): void
    {
        $action = new DeletePostAction();

        self::assertSame('delete_post', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function deletePostActionAcceptsCustomPriority(): void
    {
        $action = new DeletePostAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function savePostActionHasCorrectHookNameWithoutPostType(): void
    {
        $action = new SavePostAction();

        self::assertSame('save_post', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function savePostActionHasCorrectHookNameWithPostType(): void
    {
        $action = new SavePostAction(postType: 'product');

        self::assertSame('save_post_product', $action->hook);
    }

    #[Test]
    public function savePostActionAcceptsCustomPriority(): void
    {
        $action = new SavePostAction(priority: 20);

        self::assertSame(20, $action->priority);
    }

    #[Test]
    public function savePostActionPostTypePropertyIsAccessible(): void
    {
        $action = new SavePostAction(postType: 'product');

        self::assertSame('product', $action->postType);
    }

    #[Test]
    public function savePostActionPostTypeIsNullByDefault(): void
    {
        $action = new SavePostAction();

        self::assertNull($action->postType);
    }

    #[Test]
    public function transitionPostStatusActionHasCorrectHookName(): void
    {
        $action = new TransitionPostStatusAction();

        self::assertSame('transition_post_status', $action->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new DeletePostAction());
        self::assertInstanceOf(Action::class, new SavePostAction());
        self::assertInstanceOf(Action::class, new TransitionPostStatusAction());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[SavePostAction(postType: 'page')]
            public function onSavePage(): void {}

            #[DeletePostAction(priority: 5)]
            public function onDeletePost(): void {}
        };

        $saveMethod = new \ReflectionMethod($class, 'onSavePage');
        $attributes = $saveMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('save_post_page', $attributes[0]->newInstance()->hook);

        $deleteMethod = new \ReflectionMethod($class, 'onDeletePost');
        $attributes = $deleteMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('delete_post', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
