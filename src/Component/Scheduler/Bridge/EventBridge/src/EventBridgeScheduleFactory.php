<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

use WpPack\Component\Scheduler\Exception\InvalidArgumentException;
use WpPack\Component\Scheduler\Trigger\CronExpressionTrigger;
use WpPack\Component\Scheduler\Trigger\DateTimeTrigger;
use WpPack\Component\Scheduler\Trigger\IntervalTrigger;
use WpPack\Component\Scheduler\Trigger\JitterTrigger;
use WpPack\Component\Scheduler\Trigger\TriggerInterface;
use WpPack\Component\Scheduler\Trigger\WpCronScheduleTrigger;

final class EventBridgeScheduleFactory
{
    /**
     * Convert a TriggerInterface to an EventBridge schedule expression.
     *
     * @return array{expression: string, type: 'rate'|'cron'|'at'}
     */
    public function createExpression(TriggerInterface $trigger): array
    {
        $trigger = $this->unwrapJitter($trigger);

        return match (true) {
            $trigger instanceof IntervalTrigger,
            $trigger instanceof WpCronScheduleTrigger => $this->fromInterval($trigger->getIntervalInSeconds()),
            $trigger instanceof CronExpressionTrigger => $this->fromCronExpression((string) $trigger),
            $trigger instanceof DateTimeTrigger => $this->fromDateTime($trigger->getDateTime()),
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported trigger type "%s".',
                $trigger::class,
            )),
        };
    }

    /**
     * Convert a WP-Cron interval (seconds) to an EventBridge rate expression.
     *
     * @return array{expression: string, type: 'rate'}
     */
    public function fromWpCronInterval(int $intervalSeconds): array
    {
        return $this->fromInterval($intervalSeconds);
    }

    /**
     * Convert a Unix timestamp to an EventBridge at() expression.
     *
     * @return array{expression: string, type: 'at'}
     */
    public function fromTimestamp(int $timestamp): array
    {
        $dateTime = (new \DateTimeImmutable())
            ->setTimestamp($timestamp)
            ->setTimezone(new \DateTimeZone('UTC'));

        return $this->fromDateTime($dateTime);
    }

    /**
     * @return array{expression: string, type: 'rate'}
     */
    private function fromInterval(int $seconds): array
    {
        // EventBridge minimum granularity is 1 minute
        if ($seconds < 60) {
            return [
                'expression' => 'rate(1 minute)',
                'type' => 'rate',
            ];
        }

        if ($seconds >= 3600 && $seconds % 3600 === 0) {
            $hours = $seconds / 3600;

            return [
                'expression' => sprintf('rate(%d %s)', $hours, $hours === 1 ? 'hour' : 'hours'),
                'type' => 'rate',
            ];
        }

        if ($seconds % 60 === 0) {
            $minutes = $seconds / 60;

            return [
                'expression' => sprintf('rate(%d %s)', $minutes, $minutes === 1 ? 'minute' : 'minutes'),
                'type' => 'rate',
            ];
        }

        // Non-even minutes — round up to nearest minute
        $minutes = (int) ceil($seconds / 60);

        return [
            'expression' => sprintf('rate(%d minutes)', $minutes),
            'type' => 'rate',
        ];
    }

    /**
     * Convert a 5-field cron expression to an EventBridge 6-field cron expression.
     *
     * Standard:     min hour dom month dow
     * EventBridge:  cron(min hour dom month dow year)
     *
     * EventBridge requires either dom or dow to be '?'.
     *
     * @return array{expression: string, type: 'cron'}
     */
    public function fromCronExpression(string $expression): array
    {
        $parts = preg_split('/\s+/', trim($expression));

        if ($parts === false || \count($parts) !== 5) {
            throw new InvalidArgumentException(sprintf(
                'Expected 5-field cron expression, got: "%s".',
                $expression,
            ));
        }

        [$minute, $hour, $dom, $month, $dow] = $parts;

        // EventBridge: one of dom/dow must be '?'
        if ($dom !== '?' && $dow !== '?') {
            if ($dow === '*') {
                $dow = '?';
            } elseif ($dom === '*') {
                $dom = '?';
            } else {
                // Both specified — prefer dom, set dow to ?
                $dow = '?';
            }
        }

        return [
            'expression' => sprintf('cron(%s %s %s %s %s *)', $minute, $hour, $dom, $month, $dow),
            'type' => 'cron',
        ];
    }

    /**
     * @return array{expression: string, type: 'at'}
     */
    private function fromDateTime(\DateTimeImmutable $dateTime): array
    {
        $utc = $dateTime->setTimezone(new \DateTimeZone('UTC'));

        return [
            'expression' => sprintf('at(%s)', $utc->format('Y-m-d\TH:i:s')),
            'type' => 'at',
        ];
    }

    private function unwrapJitter(TriggerInterface $trigger): TriggerInterface
    {
        if (!$trigger instanceof JitterTrigger) {
            return $trigger;
        }

        // Extract inner trigger via reflection (JitterTrigger wraps another trigger)
        $ref = new \ReflectionClass($trigger);
        $prop = $ref->getProperty('inner');

        return $prop->getValue($trigger);
    }
}
