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

namespace WPPack\Plugin\S3StoragePlugin\Message;

use Psr\Log\LoggerInterface;

final readonly class S3EventNormalizer
{
    public function __construct(
        private ?LoggerInterface $logger = null,
    ) {}
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
                $this->logger?->debug('Skipping S3 event record with empty bucket or key.', [
                    'eventName' => (string) ($record['eventName'] ?? ''),
                ]);

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
