<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Trigger;

use WpPack\Component\Scheduler\Exception\InvalidArgumentException;

final class IntervalTrigger implements TriggerInterface
{
    public function __construct(
        private readonly int $intervalInSeconds,
        private readonly ?\DateTimeImmutable $from = null,
    ) {
        if ($intervalInSeconds <= 0) {
            throw new InvalidArgumentException('Interval must be positive.');
        }
    }

    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): \DateTimeImmutable
    {
        if ($lastRun !== null) {
            $next = $lastRun->modify("+{$this->intervalInSeconds} seconds");

            return $next > $now ? $next : $now;
        }

        $from = $this->from ?? $now;

        return $from > $now ? $from : $now;
    }

    public function getIntervalInSeconds(): int
    {
        return $this->intervalInSeconds;
    }

    public function __toString(): string
    {
        return sprintf('every %d seconds', $this->intervalInSeconds);
    }
}
