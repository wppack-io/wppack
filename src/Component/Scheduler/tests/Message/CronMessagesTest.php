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

namespace WPPack\Component\Scheduler\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Message\ActionSchedulerMessage;
use WPPack\Component\Scheduler\Message\WpCronMessage;

#[CoversClass(WpCronMessage::class)]
#[CoversClass(ActionSchedulerMessage::class)]
final class CronMessagesTest extends TestCase
{
    #[Test]
    public function wpCronMessageOneOffDefaults(): void
    {
        $msg = new WpCronMessage(hook: 'my_event');

        self::assertSame('my_event', $msg->hook);
        self::assertSame([], $msg->args);
        self::assertFalse($msg->schedule);
        self::assertSame(0, $msg->timestamp);
    }

    #[Test]
    public function wpCronMessageRecurringCarriesSchedule(): void
    {
        $msg = new WpCronMessage(
            hook: 'daily_cleanup',
            args: ['param' => 'value'],
            schedule: 'daily',
            timestamp: 1_700_000_000,
        );

        self::assertSame('daily', $msg->schedule);
        self::assertSame(1_700_000_000, $msg->timestamp);
        self::assertSame(['param' => 'value'], $msg->args);
    }

    #[Test]
    public function actionSchedulerMessageCarriesGroupAndId(): void
    {
        $msg = new ActionSchedulerMessage(
            hook: 'woo_process_order',
            args: [42, 'paid'],
            group: 'orders',
            actionId: 12345,
        );

        self::assertSame('woo_process_order', $msg->hook);
        self::assertSame([42, 'paid'], $msg->args);
        self::assertSame('orders', $msg->group);
        self::assertSame(12345, $msg->actionId);
    }

    #[Test]
    public function actionSchedulerMessageDefaults(): void
    {
        $msg = new ActionSchedulerMessage(hook: 'x');

        self::assertSame([], $msg->args);
        self::assertSame('', $msg->group);
        self::assertSame(0, $msg->actionId);
    }

    #[Test]
    public function messagesAreFinalReadonly(): void
    {
        foreach ([WpCronMessage::class, ActionSchedulerMessage::class] as $class) {
            $ref = new \ReflectionClass($class);
            self::assertTrue($ref->isFinal());
            self::assertTrue($ref->isReadOnly());
        }
    }
}
