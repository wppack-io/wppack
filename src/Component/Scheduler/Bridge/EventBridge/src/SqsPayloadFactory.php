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

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

use WpPack\Component\Messenger\Stamp\StampInterface;
use WpPack\Component\Scheduler\Message\ActionSchedulerMessage;
use WpPack\Component\Scheduler\Message\WpCronMessage;
use WpPack\Component\Serializer\Encoder\JsonEncoder;
use WpPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WpPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WpPack\Component\Serializer\Serializer;
use WpPack\Component\Serializer\SerializerInterface;

/**
 * Builds SQS message payloads compatible with SqsEventHandler + JsonSerializer::decode().
 *
 * The output JSON string becomes the EventBridge Target.Input, which is delivered
 * as the SQS message body. SqsEventHandler decodes it via JsonEncoder and feeds
 * the result to JsonSerializer::decode().
 */
final class SqsPayloadFactory
{
    private readonly SerializerInterface $serializer;
    private readonly JsonEncoder $jsonEncoder;

    public function __construct(
        ?SerializerInterface $serializer = null,
    ) {
        $this->jsonEncoder = new JsonEncoder();
        $this->serializer = $serializer ?? new Serializer(
            normalizers: [new BackedEnumNormalizer(), new DateTimeNormalizer(), new ObjectNormalizer()],
            encoders: [$this->jsonEncoder],
        );
    }

    /**
     * Create an SQS payload from a message object and optional stamps.
     *
     * @param list<StampInterface> $stamps
     */
    public function create(object $message, array $stamps = []): string
    {
        $normalizedStamps = [];
        foreach ($stamps as $stamp) {
            $normalizedStamps[$stamp::class][] = $this->serializer->normalize($stamp);
        }

        return $this->jsonEncoder->encode([
            'headers' => [
                'type' => $message::class,
                'stamps' => $normalizedStamps,
            ],
            'body' => $this->serializer->serialize($message, 'json'),
        ], 'json');
    }

    /**
     * Create an SQS payload for a WP-Cron event.
     *
     * @param array<mixed> $args
     * @param list<StampInterface> $stamps
     */
    public function createForWpCronEvent(
        string $hook,
        array $args,
        string|false $schedule,
        int $timestamp,
        array $stamps = [],
    ): string {
        return $this->create(
            new WpCronMessage(
                hook: $hook,
                args: $args,
                schedule: $schedule,
                timestamp: $timestamp,
            ),
            $stamps,
        );
    }

    /**
     * Create an SQS payload for an Action Scheduler action.
     *
     * @param array<mixed> $args
     * @param list<StampInterface> $stamps
     */
    public function createForActionSchedulerAction(
        string $hook,
        array $args,
        string $group,
        int $actionId,
        array $stamps = [],
    ): string {
        return $this->create(
            new ActionSchedulerMessage(
                hook: $hook,
                args: $args,
                group: $group,
                actionId: $actionId,
            ),
            $stamps,
        );
    }
}
