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

namespace WpPack\Component\Scheduler\Scheduler;

use WpPack\Component\Scheduler\Message\ScheduledMessage;

final class NullScheduler implements SchedulerInterface
{
    /** @var array<string, ScheduledMessage> */
    private array $schedules = [];

    public function schedule(string $scheduleId, ScheduledMessage $message): void
    {
        $this->schedules[$scheduleId] = $message;
    }

    public function unschedule(string $scheduleId): void
    {
        unset($this->schedules[$scheduleId]);
    }

    public function has(string $scheduleId): bool
    {
        return isset($this->schedules[$scheduleId]);
    }

    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable
    {
        if (!isset($this->schedules[$scheduleId])) {
            return null;
        }

        return $this->schedules[$scheduleId]->getTrigger()->getNextRunDate(new \DateTimeImmutable());
    }

    public function createScheduleRaw(
        string $scheduleId,
        string $expression,
        string $payload,
        bool $autoDelete = false,
    ): void {
        // No-op: NullScheduler does not interact with EventBridge
    }

    /** @return array<string, ScheduledMessage> */
    public function getSchedules(): array
    {
        return $this->schedules;
    }
}
