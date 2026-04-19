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

namespace WPPack\Component\Scheduler\Message;

use WPPack\Component\Scheduler\Exception\InvalidArgumentException;
use WPPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;
use WPPack\Component\Scheduler\Trigger\TriggerInterface;
use WPPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

final class RecurringMessage implements ScheduledMessage
{
    // Intentionally mutable: allows factory-chain usage e.g. RecurringMessage::every('1 hour', $msg)->name('my-task')
    private ?string $name = null;

    private function __construct(
        private readonly TriggerInterface $trigger,
        private readonly object $message,
    ) {}

    public static function schedule(string $wpCronSchedule, object $message): self
    {
        return new self(new WpCronScheduleTrigger($wpCronSchedule), $message);
    }

    public static function every(string $interval, object $message): self
    {
        $seconds = self::parseInterval($interval);

        return new self(new IntervalTrigger($seconds), $message);
    }

    public static function cron(string $expression, object $message): self
    {
        return new self(new CronExpressionTrigger($expression), $message);
    }

    public static function trigger(TriggerInterface $trigger, object $message): self
    {
        return new self($trigger, $message);
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getTrigger(): TriggerInterface
    {
        return $this->trigger;
    }

    public function getMessage(): object
    {
        return $this->message;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    private static function parseInterval(string $interval): int
    {
        // Support formats: "30 seconds", "5 minutes", "1 hour", "2 hours", "1 day", etc.
        if (preg_match('/^(\d+)\s*(second|seconds|minute|minutes|hour|hours|day|days|week|weeks)$/', trim($interval), $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid interval format "%s".', $interval));
        }

        $value = (int) $matches[1];

        return match ($matches[2]) {
            'second', 'seconds' => $value,
            'minute', 'minutes' => $value * 60,
            'hour', 'hours' => $value * 3600,
            'day', 'days' => $value * 86400,
            'week', 'weeks' => $value * 604800,
        };
    }
}
