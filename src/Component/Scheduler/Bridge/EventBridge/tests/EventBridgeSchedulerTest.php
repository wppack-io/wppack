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

namespace WPPack\Component\Scheduler\Bridge\EventBridge\Tests;

use AsyncAws\Scheduler\Exception\ConflictException;
use AsyncAws\Scheduler\Exception\ResourceNotFoundException;
use AsyncAws\Scheduler\Input\CreateScheduleInput;
use AsyncAws\Scheduler\Input\DeleteScheduleInput;
use AsyncAws\Scheduler\Input\GetScheduleInput;
use AsyncAws\Scheduler\Input\UpdateScheduleInput;
use AsyncAws\Scheduler\Result\CreateScheduleOutput;
use AsyncAws\Scheduler\Result\DeleteScheduleOutput;
use AsyncAws\Scheduler\Result\GetScheduleOutput;
use AsyncAws\Scheduler\Result\UpdateScheduleOutput;
use AsyncAws\Scheduler\SchedulerClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use WPPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduleFactory;
use WPPack\Component\Scheduler\Bridge\EventBridge\EventBridgeScheduler;
use WPPack\Component\Scheduler\Bridge\EventBridge\Exception\EventBridgeException;
use WPPack\Component\Scheduler\Bridge\EventBridge\MultisiteScheduleGroupResolver;
use WPPack\Component\Scheduler\Bridge\EventBridge\ScheduleGroupResolverInterface;
use WPPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WPPack\Component\Scheduler\Message\ScheduledMessage;
use WPPack\Component\Scheduler\Scheduler\SchedulerInterface;
use WPPack\Component\Scheduler\Trigger\IntervalTrigger;

#[CoversClass(EventBridgeScheduler::class)]
final class EventBridgeSchedulerTest extends TestCase
{
    private SchedulerClient&\PHPUnit\Framework\MockObject\MockObject $schedulerClient;
    private EventBridgeScheduler $scheduler;
    private ScheduleGroupResolverInterface $groupResolver;

    protected function setUp(): void
    {
        $this->schedulerClient = $this->createMock(SchedulerClient::class);
        $this->groupResolver = new MultisiteScheduleGroupResolver(prefix: 'test');

        $this->scheduler = new EventBridgeScheduler(
            $this->schedulerClient,
            new EventBridgeScheduleFactory(),
            new SqsPayloadFactory(),
            $this->groupResolver,
            'arn:aws:sqs:us-east-1:123456789:test-queue',
            'arn:aws:iam::123456789:role/test-role',
        );
    }

    #[Test]
    public function implementsSchedulerInterface(): void
    {
        self::assertInstanceOf(SchedulerInterface::class, $this->scheduler);
    }

    #[Test]
    public function scheduleCreatesEventBridgeScheduleWithRateExpression(): void
    {
        $trigger = new IntervalTrigger(3600);
        $message = $this->createMock(ScheduledMessage::class);
        $message->method('getTrigger')->willReturn($trigger);
        $message->method('getMessage')->willReturn(new \stdClass());

        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->with(self::callback(static function (CreateScheduleInput $input): bool {
                return $input->getName() === 'test-schedule'
                    && str_contains($input->getScheduleExpression(), 'rate(');
            }));

        $this->scheduler->schedule('test-schedule', $message);
    }

    #[Test]
    public function scheduleWithDateTimeTriggerSetsAutoDelete(): void
    {
        $dateTime = new \DateTimeImmutable('+1 hour', new \DateTimeZone('UTC'));
        $trigger = new \WPPack\Component\Scheduler\Trigger\DateTimeTrigger($dateTime);
        $message = $this->createMock(ScheduledMessage::class);
        $message->method('getTrigger')->willReturn($trigger);
        $message->method('getMessage')->willReturn(new \stdClass());

        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->with(self::callback(static function (CreateScheduleInput $input): bool {
                return str_contains($input->getScheduleExpression(), 'at(')
                    && $input->getActionAfterCompletion() === 'DELETE';
            }));

        $this->scheduler->schedule('at-schedule', $message);
    }

    #[Test]
    public function unscheduleDeletesSchedule(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('deleteSchedule')
            ->with(self::callback(static function (DeleteScheduleInput $input): bool {
                return $input->getName() === 'test-schedule'
                    && $input->getGroupName() === 'test';
            }));

        $this->scheduler->unschedule('test-schedule');
    }

    #[Test]
    public function unscheduleIgnoresResourceNotFoundException(): void
    {
        $response = $this->createMockResponse(404);
        $exception = new ResourceNotFoundException($response);

        $this->schedulerClient->expects(self::once())
            ->method('deleteSchedule')
            ->willThrowException($exception);

        // Should not throw — idempotent
        $this->scheduler->unschedule('already-deleted');
    }

    #[Test]
    public function unscheduleWrapsOtherExceptionsInEventBridgeException(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('deleteSchedule')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->expectException(EventBridgeException::class);
        $this->expectExceptionMessage('Failed to delete schedule "fail-schedule"');

        $this->scheduler->unschedule('fail-schedule');
    }

    #[Test]
    public function hasReturnsTrueWhenScheduleExists(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('getSchedule')
            ->with(self::callback(static function (GetScheduleInput $input): bool {
                return $input->getName() === 'existing-schedule'
                    && $input->getGroupName() === 'test';
            }));

        self::assertTrue($this->scheduler->has('existing-schedule'));
    }

    #[Test]
    public function hasReturnsFalseWhenResourceNotFound(): void
    {
        $response = $this->createMockResponse(404);
        $exception = new ResourceNotFoundException($response);

        $this->schedulerClient->expects(self::once())
            ->method('getSchedule')
            ->willThrowException($exception);

        self::assertFalse($this->scheduler->has('nonexistent'));
    }

    #[Test]
    public function hasWrapsOtherExceptionsInEventBridgeException(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('getSchedule')
            ->willThrowException(new \RuntimeException('API error'));

        $this->expectException(EventBridgeException::class);
        $this->expectExceptionMessage('Failed to check schedule "check-fail"');

        $this->scheduler->has('check-fail');
    }

    #[Test]
    public function getNextRunDateAlwaysReturnsNull(): void
    {
        self::assertNull($this->scheduler->getNextRunDate('any-schedule'));
    }

    #[Test]
    public function createScheduleRawCreatesSchedule(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->with(self::callback(static function (CreateScheduleInput $input): bool {
                return $input->getName() === 'raw-schedule'
                    && $input->getGroupName() === 'test'
                    && $input->getScheduleExpression() === 'rate(1 hour)'
                    && $input->getScheduleExpressionTimezone() === 'UTC'
                    && $input->getTarget()->getArn() === 'arn:aws:sqs:us-east-1:123456789:test-queue'
                    && $input->getTarget()->getRoleArn() === 'arn:aws:iam::123456789:role/test-role'
                    && $input->getTarget()->getInput() === '{"key":"value"}'
                    && $input->getFlexibleTimeWindow()->getMode() === 'OFF';
            }));

        $this->scheduler->createScheduleRaw('raw-schedule', 'rate(1 hour)', '{"key":"value"}');
    }

    #[Test]
    public function createScheduleRawWithAutoDeleteSetsActionAfterCompletion(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->with(self::callback(static function (CreateScheduleInput $input): bool {
                return $input->getActionAfterCompletion() === 'DELETE';
            }));

        $this->scheduler->createScheduleRaw('auto-delete', 'at(2025-01-01T00:00:00)', '{}', true);
    }

    #[Test]
    public function createScheduleRawWithoutAutoDeleteDoesNotSetActionAfterCompletion(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->with(self::callback(static function (CreateScheduleInput $input): bool {
                return $input->getActionAfterCompletion() === null;
            }));

        $this->scheduler->createScheduleRaw('no-auto-delete', 'rate(1 hour)', '{}', false);
    }

    #[Test]
    public function createScheduleRawFallsBackToUpdateOnConflict(): void
    {
        $response = $this->createMockResponse(409);
        $conflictException = new ConflictException($response);

        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->willThrowException($conflictException);

        $this->schedulerClient->expects(self::once())
            ->method('updateSchedule')
            ->with(self::callback(static function (UpdateScheduleInput $input): bool {
                return $input->getName() === 'conflict-schedule'
                    && $input->getScheduleExpression() === 'rate(5 minutes)';
            }));

        $this->scheduler->createScheduleRaw('conflict-schedule', 'rate(5 minutes)', '{}');
    }

    #[Test]
    public function createScheduleRawThrowsEventBridgeExceptionWhenUpdateFails(): void
    {
        $response = $this->createMockResponse(409);
        $conflictException = new ConflictException($response);

        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->willThrowException($conflictException);

        $this->schedulerClient->expects(self::once())
            ->method('updateSchedule')
            ->willThrowException(new \RuntimeException('Update failed'));

        $this->expectException(EventBridgeException::class);
        $this->expectExceptionMessage('Failed to update schedule "update-fail"');

        $this->scheduler->createScheduleRaw('update-fail', 'rate(1 hour)', '{}');
    }

    #[Test]
    public function createScheduleRawThrowsEventBridgeExceptionOnOtherErrors(): void
    {
        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->willThrowException(new \RuntimeException('Create failed'));

        $this->expectException(EventBridgeException::class);
        $this->expectExceptionMessage('Failed to create schedule "create-fail"');

        $this->scheduler->createScheduleRaw('create-fail', 'rate(1 hour)', '{}');
    }

    #[Test]
    public function createScheduleRawPreservesPreviousExceptionOnCreateFailure(): void
    {
        $original = new \RuntimeException('Original error');

        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->willThrowException($original);

        try {
            $this->scheduler->createScheduleRaw('preserve-prev', 'rate(1 hour)', '{}');
            self::fail('Expected EventBridgeException');
        } catch (EventBridgeException $e) {
            self::assertSame($original, $e->getPrevious());
        }
    }

    #[Test]
    public function createScheduleRawPreservesPreviousExceptionOnUpdateFailure(): void
    {
        $response = $this->createMockResponse(409);
        $conflictException = new ConflictException($response);
        $updateError = new \RuntimeException('Update error');

        $this->schedulerClient->expects(self::once())
            ->method('createSchedule')
            ->willThrowException($conflictException);

        $this->schedulerClient->expects(self::once())
            ->method('updateSchedule')
            ->willThrowException($updateError);

        try {
            $this->scheduler->createScheduleRaw('preserve-update', 'rate(1 hour)', '{}');
            self::fail('Expected EventBridgeException');
        } catch (EventBridgeException $e) {
            self::assertSame($updateError, $e->getPrevious());
        }
    }

    #[Test]
    public function unschedulePreservesPreviousException(): void
    {
        $original = new \RuntimeException('Delete error');

        $this->schedulerClient->expects(self::once())
            ->method('deleteSchedule')
            ->willThrowException($original);

        try {
            $this->scheduler->unschedule('preserve-delete');
            self::fail('Expected EventBridgeException');
        } catch (EventBridgeException $e) {
            self::assertSame($original, $e->getPrevious());
        }
    }

    #[Test]
    public function hasPreservesPreviousException(): void
    {
        $original = new \RuntimeException('Get error');

        $this->schedulerClient->expects(self::once())
            ->method('getSchedule')
            ->willThrowException($original);

        try {
            $this->scheduler->has('preserve-get');
            self::fail('Expected EventBridgeException');
        } catch (EventBridgeException $e) {
            self::assertSame($original, $e->getPrevious());
        }
    }

    private function createMockResponse(int $statusCode): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getInfo')->willReturnCallback(static function (?string $type) use ($statusCode): mixed {
            return match ($type) {
                'http_code' => $statusCode,
                'url' => 'https://scheduler.us-east-1.amazonaws.com/test',
                default => null,
            };
        });
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getHeaders')->willReturn([]);
        $response->method('getContent')->willReturn('');

        return $response;
    }
}
