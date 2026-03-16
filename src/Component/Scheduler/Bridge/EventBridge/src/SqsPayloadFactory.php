<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

use WpPack\Component\Messenger\Stamp\StampInterface;
use WpPack\Component\Scheduler\Message\WpCronMessage;

/**
 * Builds SQS message payloads compatible with SqsEventHandler + JsonSerializer::decode().
 *
 * The output JSON string becomes the EventBridge Target.Input, which is delivered
 * as the SQS message body. SqsEventHandler json_decodes it and feeds the result
 * to JsonSerializer::decode().
 */
final class SqsPayloadFactory
{
    /**
     * Create an SQS payload from a message object and optional stamps.
     *
     * @param list<StampInterface> $stamps
     */
    public function create(object $message, array $stamps = []): string
    {
        $normalizedStamps = [];
        foreach ($stamps as $stamp) {
            $normalizedStamps[$stamp::class][] = $this->normalizeStamp($stamp);
        }

        return json_encode([
            'headers' => [
                'type' => $message::class,
                'stamps' => $normalizedStamps,
            ],
            'body' => json_encode(
                $this->normalizeMessage($message),
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
            ),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
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
     * @return array<string, mixed>
     */
    private function normalizeMessage(object $message): array
    {
        $ref = new \ReflectionClass($message);
        $data = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $data[$prop->getName()] = $prop->getValue($message);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeStamp(StampInterface $stamp): array
    {
        $ref = new \ReflectionClass($stamp);
        $data = [];

        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $data[$prop->getName()] = $prop->getValue($stamp);
        }

        return $data;
    }
}
