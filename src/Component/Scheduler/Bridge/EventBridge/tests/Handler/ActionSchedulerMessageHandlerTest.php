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

namespace WPPack\Component\Scheduler\Bridge\EventBridge\Tests\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\Scheduler\Bridge\EventBridge\Handler\ActionSchedulerMessageHandler;
use WPPack\Component\Scheduler\Message\ActionSchedulerMessage;

final class ActionSchedulerMessageHandlerTest extends TestCase
{
    private ActionSchedulerMessageHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ActionSchedulerMessageHandler();
    }

    #[Test]
    public function invokeCallsDoActionRefArray(): void
    {
        $called = false;
        $receivedArgs = [];

        $callback = static function () use (&$called, &$receivedArgs): void {
            $called = true;
            $receivedArgs = \func_get_args();
        };

        add_action('test_as_handler_hook', $callback, 10, 2);

        try {
            $message = new ActionSchedulerMessage(
                hook: 'test_as_handler_hook',
                args: ['value1', 'value2'],
                group: 'test-group',
                actionId: 0,
            );

            ($this->handler)($message);

            self::assertTrue($called, 'do_action_ref_array should have called the hook callback');
            self::assertSame(['value1', 'value2'], $receivedArgs);
        } finally {
            remove_action('test_as_handler_hook', $callback, 10);
        }
    }

    #[Test]
    public function invokeMarksActionCompleteInStore(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $actionId = as_schedule_single_action(time() + 3600, 'test_as_handler_complete_hook', [], 'test-group');

        $message = new ActionSchedulerMessage(
            hook: 'test_as_handler_complete_hook',
            args: [],
            group: 'test-group',
            actionId: $actionId,
        );

        ($this->handler)($message);

        $action = \ActionScheduler::store()->fetch_action($actionId);
        self::assertTrue($action->is_finished());
    }

    #[Test]
    public function invokeDoesNotFailWithZeroActionId(): void
    {
        $message = new ActionSchedulerMessage(
            hook: 'test_as_handler_noop_hook',
            args: [],
            group: '',
            actionId: 0,
        );

        // Should not throw
        ($this->handler)($message);

        self::assertTrue(true);
    }

    #[Test]
    public function handlerHasAsMessageHandlerAttribute(): void
    {
        $ref = new \ReflectionClass(ActionSchedulerMessageHandler::class);
        $attributes = $ref->getAttributes(\WPPack\Component\Messenger\Attribute\AsMessageHandler::class);

        self::assertCount(1, $attributes);
    }

    #[Test]
    public function invokeLogsWarningWhenMarkCompleteThrows(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')
            ->with(
                self::stringContains('Failed to mark Action Scheduler action'),
                self::callback(static function (array $context): bool {
                    return isset($context['actionId'], $context['error'], $context['hook']);
                }),
            );

        $handler = new ActionSchedulerMessageHandler($logger);

        // Use a non-existent actionId to cause mark_complete to throw
        // InvalidArgumentException ("Unidentified action")
        $message = new ActionSchedulerMessage(
            hook: 'test_as_handler_fail_complete_hook',
            args: [],
            group: 'test-group',
            actionId: 999999999,
        );

        // Should not throw - warning is logged instead
        ($handler)($message);
    }

    #[Test]
    public function invokeSkipsMarkCompleteWhenActionSchedulerNotAvailableButActionIdPositive(): void
    {
        // This test validates the class_exists check path
        // When ActionScheduler IS available, actionId > 0 enters the mark_complete branch
        // When ActionScheduler is NOT available, it skips the branch entirely
        // In either case, no exception should be thrown
        $message = new ActionSchedulerMessage(
            hook: 'test_as_handler_conditional_hook',
            args: [],
            group: '',
            actionId: 1,
        );

        // With ActionScheduler available: will try mark_complete(1) on a non-existent action
        // and either succeed or get caught by the try/catch
        $logger = $this->createMock(LoggerInterface::class);
        $handler = new ActionSchedulerMessageHandler($logger);

        // Should not throw regardless of AS availability
        ($handler)($message);

        self::assertTrue(true);
    }
}
