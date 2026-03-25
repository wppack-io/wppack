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

interface SchedulerInterface
{
    public function schedule(string $scheduleId, ScheduledMessage $message): void;

    public function unschedule(string $scheduleId): void;

    public function has(string $scheduleId): bool;

    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable;

    /**
     * Create or update a schedule from raw EventBridge parameters.
     *
     * Used by interceptors to create schedules from WP-Cron / Action Scheduler
     * parameters without constructing a ScheduledMessage.
     */
    public function createScheduleRaw(
        string $scheduleId,
        string $expression,
        string $payload,
        bool $autoDelete = false,
    ): void;
}
