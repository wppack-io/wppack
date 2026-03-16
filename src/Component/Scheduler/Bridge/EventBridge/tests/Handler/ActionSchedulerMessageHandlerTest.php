<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests\Handler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Bridge\EventBridge\Handler\ActionSchedulerMessageHandler;
use WpPack\Component\Scheduler\Message\ActionSchedulerMessage;

final class ActionSchedulerMessageHandlerTest extends TestCase
{
    private ActionSchedulerMessageHandler $handler;

    protected function setUp(): void
    {
        if (!\function_exists('do_action_ref_array')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->handler = new ActionSchedulerMessageHandler();
    }

    #[Test]
    public function invokeCallsDoActionRefArray(): void
    {
        $called = false;
        $receivedArgs = [];

        add_action('test_as_hook', static function () use (&$called, &$receivedArgs): void {
            $called = true;
            $receivedArgs = \func_get_args();
        }, 10, 2);

        try {
            $message = new ActionSchedulerMessage(
                hook: 'test_as_hook',
                args: ['value1', 'value2'],
                group: 'test-group',
                actionId: 0,
            );

            ($this->handler)($message);

            self::assertTrue($called, 'do_action_ref_array should have called the hook callback');
            self::assertSame(['value1', 'value2'], $receivedArgs);
        } finally {
            remove_action('test_as_hook', static function (): void {}, 10);
        }
    }

    #[Test]
    public function invokeMarksActionCompleteInStore(): void
    {
        if (!class_exists(\ActionScheduler::class)) {
            self::markTestSkipped('Action Scheduler is not available.');
        }

        $actionId = as_schedule_single_action(time() + 3600, 'test_as_complete_hook', [], 'test-group');

        $message = new ActionSchedulerMessage(
            hook: 'test_as_complete_hook',
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
            hook: 'test_as_noop_hook',
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
        $attributes = $ref->getAttributes(\WpPack\Component\Messenger\Attribute\AsMessageHandler::class);

        self::assertCount(1, $attributes);
    }
}
