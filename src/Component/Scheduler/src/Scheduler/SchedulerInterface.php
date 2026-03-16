<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Scheduler;

use WpPack\Component\Scheduler\Message\ScheduledMessage;

interface SchedulerInterface
{
    public function schedule(string $scheduleId, ScheduledMessage $message): void;

    public function unschedule(string $scheduleId): void;

    public function has(string $scheduleId): bool;

    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable;
}
