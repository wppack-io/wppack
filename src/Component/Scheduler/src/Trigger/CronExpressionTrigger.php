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

use Cron\CronExpression;

final class CronExpressionTrigger implements TriggerInterface
{
    private readonly CronExpression $expression;

    public function __construct(string $expression)
    {
        $this->expression = new CronExpression($expression);
    }

    public function getNextRunDate(\DateTimeImmutable $now, ?\DateTimeImmutable $lastRun = null): \DateTimeImmutable
    {
        $next = $this->expression->getNextRunDate($lastRun ?? $now);

        return \DateTimeImmutable::createFromMutable($next);
    }

    public function getIntervalInSeconds(): ?int
    {
        return null; // Cron expressions don't have a fixed interval
    }

    public function __toString(): string
    {
        return $this->expression->getExpression();
    }
}
