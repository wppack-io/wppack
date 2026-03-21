<?php

declare(strict_types=1);

namespace WpPack\Plugin\S3StoragePlugin\Message;

final readonly class S3EventNormalizer
{
    /**
     * Parse S3 Event Notification JSON into message objects.
     *
     * @param array<string, mixed> $event
     * @return list<S3ObjectCreatedMessage|S3ObjectRemovedMessage>
     */
    public function normalize(array $event): array
    {
        /** @var list<array<string, mixed>> $records */
        $records = $event['Records'] ?? [];

        $messages = [];

        foreach ($records as $record) {
            /** @var array<string, mixed> $s3Data */
            $s3Data = $record['s3'] ?? [];

            /** @var array<string, mixed> $bucketData */
            $bucketData = $s3Data['bucket'] ?? [];

            /** @var array<string, mixed> $objectData */
            $objectData = $s3Data['object'] ?? [];

            $bucket = (string) ($bucketData['name'] ?? '');
            $key = urldecode((string) ($objectData['key'] ?? ''));

            if ($bucket === '' || $key === '') {
                continue;
            }

            if ($this->isObjectCreatedEvent($record)) {
                $messages[] = new S3ObjectCreatedMessage(
                    bucket: $bucket,
                    key: $key,
                    size: (int) ($objectData['size'] ?? 0),
                    eTag: (string) ($objectData['eTag'] ?? ''),
                );
            } elseif ($this->isObjectRemovedEvent($record)) {
                $messages[] = new S3ObjectRemovedMessage(
                    bucket: $bucket,
                    key: $key,
                );
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isObjectCreatedEvent(array $record): bool
    {
        $eventName = (string) ($record['eventName'] ?? '');

        return str_starts_with($eventName, 's3:ObjectCreated:');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function isObjectRemovedEvent(array $record): bool
    {
        $eventName = (string) ($record['eventName'] ?? '');

        return str_starts_with($eventName, 's3:ObjectRemoved:');
    }
}
