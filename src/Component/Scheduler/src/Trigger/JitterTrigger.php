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

namespace WpPack\Component\Scheduler\Trigger;

final class JitterTrigger implements TriggerInterface
{
    public function __construct(
        private readonly TriggerInterface $inner,
        private readonly int $maxJitterSeconds = 60,
    ) {}

    public function getInnerTrigger(): TriggerInterface
    {
        return $this->inner;
    }

    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): ?\DateTimeImmutable
    {
        $next = $this->inner->getNextRunDate($now, $lastRun);

        if ($next === null) {
            return null;
        }

        $jitter = random_int(0, $this->maxJitterSeconds);

        return $next->modify("+{$jitter} seconds");
    }

    public function getIntervalInSeconds(): ?int
    {
        return $this->inner->getIntervalInSeconds();
    }

    public function __toString(): string
    {
        return $this->inner->__toString() . ' (with jitter)';
    }
}
