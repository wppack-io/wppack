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

namespace WPPack\Component\Scheduler\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scheduler\Message\OneTimeMessage;
use WPPack\Component\Scheduler\Message\RecurringMessage;
use WPPack\Component\Scheduler\Schedule;

#[CoversClass(Schedule::class)]
final class ScheduleTest extends TestCase
{
    #[Test]
    public function newScheduleIsEmpty(): void
    {
        self::assertSame([], (new Schedule())->getMessages());
    }

    #[Test]
    public function addAppendsMessageAndReturnsSelfForChaining(): void
    {
        $schedule = new Schedule();
        $m1 = OneTimeMessage::delaySeconds(60, new \stdClass());
        $m2 = RecurringMessage::every('1 hour', new \stdClass());

        $result = $schedule->add($m1)->add($m2);

        self::assertSame($schedule, $result);
        self::assertSame([$m1, $m2], $schedule->getMessages());
    }

    #[Test]
    public function messagesRetainInsertionOrder(): void
    {
        $schedule = new Schedule();
        $messages = [];

        foreach (range(1, 5) as $i) {
            $msg = OneTimeMessage::delaySeconds($i * 60, new \stdClass());
            $schedule->add($msg);
            $messages[] = $msg;
        }

        self::assertSame($messages, $schedule->getMessages());
    }
}
