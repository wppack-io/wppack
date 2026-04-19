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

namespace WPPack\Component\Scheduler\Bridge\EventBridge;

use AsyncAws\Scheduler\Exception\ConflictException;
use AsyncAws\Scheduler\Exception\ResourceNotFoundException;
use AsyncAws\Scheduler\Input\CreateScheduleInput;
use AsyncAws\Scheduler\Input\DeleteScheduleInput;
use AsyncAws\Scheduler\Input\GetScheduleInput;
use AsyncAws\Scheduler\Input\UpdateScheduleInput;
use AsyncAws\Scheduler\SchedulerClient;
use WPPack\Component\Scheduler\Bridge\EventBridge\Exception\EventBridgeException;
use WPPack\Component\Scheduler\Message\ScheduledMessage;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;

final class EventBridgeScheduler implements SchedulerInterface
{
    public function __construct(
        private readonly SchedulerClient $schedulerClient,
        private readonly EventBridgeScheduleFactory $scheduleFactory,
        private readonly SqsPayloadFactory $payloadFactory,
        private readonly ScheduleGroupResolverInterface $groupResolver,
        private readonly string $targetArn,
        private readonly string $roleArn,
    ) {}

    public function schedule(string $scheduleId, ScheduledMessage $message): void
    {
        $expression = $this->scheduleFactory->createExpression($message->getTrigger());
        $payload = $this->payloadFactory->create($message->getMessage());

        $this->createScheduleRaw(
            $scheduleId,
            $expression['expression'],
            $payload,
            $expression['type'] === 'at',
        );
    }

    public function unschedule(string $scheduleId): void
    {
        try {
            $this->schedulerClient->deleteSchedule(new DeleteScheduleInput([
                'Name' => $scheduleId,
                'GroupName' => $this->groupResolver->resolve(),
            ]));
        } catch (ResourceNotFoundException) {
            // Already deleted — idempotent
        } catch (\Throwable $e) {
            throw new EventBridgeException(
                sprintf('Failed to delete schedule "%s": %s', $scheduleId, $e->getMessage()),
                previous: $e,
            );
        }
    }

    public function has(string $scheduleId): bool
    {
        try {
            $this->schedulerClient->getSchedule(new GetScheduleInput([
                'Name' => $scheduleId,
                'GroupName' => $this->groupResolver->resolve(),
            ]));

            return true;
        } catch (ResourceNotFoundException) {
            return false;
        } catch (\Throwable $e) {
            throw new EventBridgeException(
                sprintf('Failed to check schedule "%s": %s', $scheduleId, $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * EventBridge Scheduler API does not expose the next execution time.
     * Use the local wp_options.cron for this information instead.
     */
    public function getNextRunDate(string $scheduleId): ?\DateTimeImmutable
    {
        return null;
    }

    /**
     * Create or update an EventBridge schedule (upsert).
     *
     * Used by WpCronInterceptor to create schedules from raw WP-Cron parameters.
     * Falls back to UpdateScheduleInput on ConflictException for idempotent upsert.
     */
    public function createScheduleRaw(
        string $scheduleId,
        string $expression,
        string $payload,
        bool $autoDelete = false,
    ): void {
        $input = [
            'Name' => $scheduleId,
            'GroupName' => $this->groupResolver->resolve(),
            'ScheduleExpression' => $expression,
            'ScheduleExpressionTimezone' => 'UTC',
            'FlexibleTimeWindow' => ['Mode' => 'OFF'],
            'Target' => [
                'Arn' => $this->targetArn,
                'RoleArn' => $this->roleArn,
                'Input' => $payload,
            ],
        ];

        if ($autoDelete) {
            $input['ActionAfterCompletion'] = 'DELETE';
        }

        try {
            $this->schedulerClient->createSchedule(new CreateScheduleInput($input));
        } catch (ConflictException) {
            try {
                $this->schedulerClient->updateSchedule(new UpdateScheduleInput($input));
            } catch (\Throwable $e) {
                throw new EventBridgeException(
                    sprintf('Failed to update schedule "%s": %s', $scheduleId, $e->getMessage()),
                    previous: $e,
                );
            }
        } catch (\Throwable $e) {
            throw new EventBridgeException(
                sprintf('Failed to create schedule "%s": %s', $scheduleId, $e->getMessage()),
                previous: $e,
            );
        }
    }
}
