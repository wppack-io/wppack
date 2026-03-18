<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\Attribute\Comment;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Hook\Attribute\Comment\Action\CommentPostAction;
use WpPack\Component\Hook\Attribute\Comment\Action\DeleteCommentAction;
use WpPack\Component\Hook\Attribute\Comment\Action\EditCommentAction;
use WpPack\Component\Hook\Attribute\Comment\Action\TransitionCommentStatusAction;
use WpPack\Component\Hook\Attribute\Comment\Action\WpInsertCommentAction;
use WpPack\Component\Hook\Attribute\Comment\Filter\PreCommentApprovedFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function commentPostActionHasCorrectHookName(): void
    {
        $action = new CommentPostAction();

        self::assertSame('comment_post', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function deleteCommentActionHasCorrectHookName(): void
    {
        $action = new DeleteCommentAction();

        self::assertSame('delete_comment', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function editCommentActionHasCorrectHookName(): void
    {
        $action = new EditCommentAction();

        self::assertSame('edit_comment', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function transitionCommentStatusActionHasCorrectHookName(): void
    {
        $action = new TransitionCommentStatusAction();

        self::assertSame('transition_comment_status', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function wpInsertCommentActionHasCorrectHookName(): void
    {
        $action = new WpInsertCommentAction();

        self::assertSame('wp_insert_comment', $action->hook);
        self::assertSame(HookType::Action, $action->type);
    }

    #[Test]
    public function preCommentApprovedFilterHasCorrectHookName(): void
    {
        $filter = new PreCommentApprovedFilter();

        self::assertSame('pre_comment_approved', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function commentPostActionAcceptsCustomPriority(): void
    {
        $action = new CommentPostAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new CommentPostAction());
        self::assertInstanceOf(Action::class, new DeleteCommentAction());
        self::assertInstanceOf(Action::class, new EditCommentAction());
        self::assertInstanceOf(Action::class, new TransitionCommentStatusAction());
        self::assertInstanceOf(Action::class, new WpInsertCommentAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new PreCommentApprovedFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[CommentPostAction]
            public function onCommentPost(): void {}

            #[PreCommentApprovedFilter]
            public function onPreCommentApproved(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onCommentPost');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('comment_post', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onPreCommentApproved');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('pre_comment_approved', $attributes[0]->newInstance()->hook);
    }
}
