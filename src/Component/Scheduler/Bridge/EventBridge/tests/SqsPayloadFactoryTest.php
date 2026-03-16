<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Serializer\JsonSerializer;
use WpPack\Component\Messenger\Stamp\MultisiteStamp;
use WpPack\Component\Scheduler\Bridge\EventBridge\SqsPayloadFactory;
use WpPack\Component\Scheduler\Message\WpCronMessage;

final class SqsPayloadFactoryTest extends TestCase
{
    private SqsPayloadFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new SqsPayloadFactory();
    }

    #[Test]
    public function createProducesValidJson(): void
    {
        $message = new WpCronMessage(hook: 'my_hook', args: ['arg1']);
        $payload = $this->factory->create($message);

        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('headers', $decoded);
        self::assertArrayHasKey('body', $decoded);
        self::assertSame(WpCronMessage::class, $decoded['headers']['type']);
    }

    #[Test]
    public function createPayloadIsCompatibleWithJsonSerializer(): void
    {
        $message = new WpCronMessage(
            hook: 'my_cron_hook',
            args: ['value1', 42],
            schedule: 'hourly',
            timestamp: 1700000000,
        );

        $payload = $this->factory->create($message);

        // Simulate SqsEventHandler: json_decode the SQS body, then pass to serializer
        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        $serializer = new JsonSerializer();
        $envelope = $serializer->decode($data);

        $decoded = $envelope->getMessage();

        self::assertInstanceOf(WpCronMessage::class, $decoded);
        self::assertSame('my_cron_hook', $decoded->hook);
        self::assertSame(['value1', 42], $decoded->args);
        self::assertSame('hourly', $decoded->schedule);
        self::assertSame(1700000000, $decoded->timestamp);
    }

    #[Test]
    public function createWithStampsIncludesStampsInHeaders(): void
    {
        $message = new WpCronMessage(hook: 'hook');
        $stamps = [new MultisiteStamp(blogId: 5)];

        $payload = $this->factory->create($message, $stamps);

        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey(MultisiteStamp::class, $decoded['headers']['stamps']);
        self::assertSame(5, $decoded['headers']['stamps'][MultisiteStamp::class][0]['blogId']);
    }

    #[Test]
    public function createWithStampsRoundTripsViaJsonSerializer(): void
    {
        $message = new WpCronMessage(hook: 'hook', args: [], schedule: 'daily', timestamp: 1700000000);
        $stamps = [new MultisiteStamp(blogId: 3)];

        $payload = $this->factory->create($message, $stamps);

        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        $serializer = new JsonSerializer();
        $envelope = $serializer->decode($data);

        $multisiteStamp = $envelope->last(MultisiteStamp::class);
        self::assertNotNull($multisiteStamp);
        self::assertSame(3, $multisiteStamp->blogId);
    }

    #[Test]
    public function createForWpCronEventProducesCorrectPayload(): void
    {
        $payload = $this->factory->createForWpCronEvent(
            hook: 'my_hook',
            args: ['a', 'b'],
            schedule: 'daily',
            timestamp: 1700000000,
        );

        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        $serializer = new JsonSerializer();
        $envelope = $serializer->decode($data);

        $decoded = $envelope->getMessage();
        self::assertInstanceOf(WpCronMessage::class, $decoded);
        self::assertSame('my_hook', $decoded->hook);
        self::assertSame(['a', 'b'], $decoded->args);
        self::assertSame('daily', $decoded->schedule);
        self::assertSame(1700000000, $decoded->timestamp);
    }

    #[Test]
    public function createForSingleEventWithFalseSchedule(): void
    {
        $payload = $this->factory->createForWpCronEvent(
            hook: 'one_time_hook',
            args: [],
            schedule: false,
            timestamp: 1700000000,
        );

        $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        $serializer = new JsonSerializer();
        $envelope = $serializer->decode($data);

        $decoded = $envelope->getMessage();
        self::assertInstanceOf(WpCronMessage::class, $decoded);
        self::assertFalse($decoded->schedule);
    }

    #[Test]
    public function createWithEmptyStampsProducesEmptyStampsArray(): void
    {
        $message = new WpCronMessage(hook: 'hook');
        $payload = $this->factory->create($message, []);

        $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        self::assertEmpty($decoded['headers']['stamps']);
    }
}
