<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Trigger;

final class DateTimeTrigger implements TriggerInterface
{
    public function __construct(
        private readonly \DateTimeImmutable $dateTime,
    ) {}

    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): ?\DateTimeImmutable
    {
        // Already fired
        if ($lastRun !== null) {
            return null;
        }

        // Already past
        if ($this->dateTime <= $now) {
            return null;
        }

        return $this->dateTime;
    }

    public function getIntervalInSeconds(): ?int
    {
        return null;
    }

    public function getDateTime(): \DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function __toString(): string
    {
        return $this->dateTime->format(\DateTimeInterface::ATOM);
    }
}
