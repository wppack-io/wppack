<?php

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

    /** @return array<string, ScheduledMessage> */
    public function getSchedules(): array
    {
        return $this->schedules;
    }
}
