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

namespace WpPack\Component\Scheduler\Message;

use WpPack\Component\Scheduler\Exception\InvalidArgumentException;
use WpPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WpPack\Component\Scheduler\Trigger\TriggerInterface;

final class OneTimeMessage implements ScheduledMessage
{
    private ?string $name = null;

    private function __construct(
        private readonly TriggerInterface $trigger,
        private readonly object $message,
    ) {}

    public static function at(\DateTimeImmutable $dateTime, object $message): self
    {
        return new self(new DateTimeTrigger($dateTime), $message);
    }

    public static function delay(string $delay, object $message): self
    {
        $seconds = self::parseDelay($delay);
        $dateTime = (new \DateTimeImmutable())->modify("+{$seconds} seconds");

        return new self(new DateTimeTrigger($dateTime), $message);
    }

    public static function delaySeconds(int $seconds, object $message): self
    {
        $dateTime = (new \DateTimeImmutable())->modify("+{$seconds} seconds");

        return new self(new DateTimeTrigger($dateTime), $message);
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

    private static function parseDelay(string $delay): int
    {
        if (preg_match('/^(\d+)\s*(second|seconds|minute|minutes|hour|hours|day|days)$/', trim($delay), $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid delay format "%s".', $delay));
        }

        $value = (int) $matches[1];

        return match ($matches[2]) {
            'second', 'seconds' => $value,
            'minute', 'minutes' => $value * 60,
            'hour', 'hours' => $value * 3600,
            'day', 'days' => $value * 86400,
        };
    }
}
