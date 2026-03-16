<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Trigger;

interface TriggerInterface extends \Stringable
{
    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): ?\DateTimeImmutable;

    public function getIntervalInSeconds(): ?int;

    public function __toString(): string;
}
