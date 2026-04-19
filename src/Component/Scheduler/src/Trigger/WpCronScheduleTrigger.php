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

namespace WPPack\Component\Scheduler\Trigger;

use WPPack\Component\Scheduler\Exception\InvalidArgumentException;

final class WpCronScheduleTrigger implements TriggerInterface
{
    private const SCHEDULES = [
        'hourly' => 3600,
        'twicedaily' => 43200,
        'daily' => 86400,
        'weekly' => 604800,
    ];

    private readonly int $intervalInSeconds;

    public function __construct(
        private readonly string $schedule,
    ) {
        if (isset(self::SCHEDULES[$this->schedule])) {
            $this->intervalInSeconds = self::SCHEDULES[$this->schedule];

            return;
        }

        // Fallback to WordPress registered schedules (e.g. fifteen_minutes, plugin-defined)
        $wpSchedules = wp_get_schedules();
        if (isset($wpSchedules[$this->schedule]['interval'])) {
            $this->intervalInSeconds = (int) $wpSchedules[$this->schedule]['interval'];

            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Unknown WP-Cron schedule "%s". Known schedules: %s.',
            $this->schedule,
            implode(', ', array_keys(self::SCHEDULES)),
        ));
    }

    public function getScheduleName(): string
    {
        return $this->schedule;
    }

    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): \DateTimeImmutable
    {
        if ($lastRun !== null) {
            $next = $lastRun->modify("+{$this->intervalInSeconds} seconds");

            return $next > $now ? $next : $now;
        }

        return $now;
    }

    public function getIntervalInSeconds(): int
    {
        return $this->intervalInSeconds;
    }

    public function __toString(): string
    {
        return $this->schedule;
    }
}
