<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Bridge\Sqs\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Messenger\Serializer\SerializerInterface;
use WpPack\Component\Messenger\Stamp\ReceivedStamp;
use WpPack\Component\Serializer\Encoder\JsonEncoder;

final class SqsEventHandler
{
    /**
     * Tracks whether WordPress has already been bootstrapped in this process.
     * Lambda reuses containers across invocations — this flag prevents loading
     * wp-load.php multiple times within the same container lifecycle.
     */
    private static bool $booted = false;

    public function __construct(
        private readonly string $wordpressPath,
        private readonly MessageBusInterface $messageBus,
        private readonly SerializerInterface $serializer,
        private readonly ?LoggerInterface $logger = null,
        private readonly JsonEncoder $jsonEncoder = new JsonEncoder(),
    ) {}

    /**
     * Lambda handler for SQS events.
     *
     * Returns partial batch failure response so only failed messages are retried.
     *
     * @param array{Records?: list<array{messageId: string, body: string}>} $event
     *
     * @return array{batchItemFailures: list<array{itemIdentifier: string}>}
     */
    public function __invoke(array $event): array
    {
        $this->bootstrap();

        $failures = [];

        foreach ($event['Records'] ?? [] as $record) {
            $messageId = $record['messageId'];

            try {
                $this->processRecord($record);
            } catch (\Throwable $e) {
                $this->logger?->error('Failed to process SQS message {messageId}: {error}', [
                    'messageId' => $messageId,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);

                $failures[] = ['itemIdentifier' => $messageId];
            }
        }

        return ['batchItemFailures' => $failures];
    }

    /**
     * @param array{messageId: string, body: string} $record
     */
    private function processRecord(array $record): void
    {
        $data = $this->jsonEncoder->decode($record['body'], 'json');
        $envelope = $this->serializer->decode($data);

        $multisiteStamp = $envelope->last(\WpPack\Component\Messenger\Stamp\MultisiteStamp::class);
        $switched = false;

        if ($multisiteStamp !== null && function_exists('switch_to_blog')) {
            switch_to_blog($multisiteStamp->blogId);
            $switched = true;
        }

        try {
            $existingStamps = $envelope->all(); // @phpstan-ignore argument.templateType
            $this->messageBus->dispatch(
                $envelope->getMessage(),
                [...$existingStamps, new ReceivedStamp('sqs')],
            );

            $this->logger?->info('Successfully processed SQS message {messageId}', [
                'messageId' => $record['messageId'],
                'messageClass' => $envelope->getMessage()::class,
            ]);
        } finally {
            if ($switched && function_exists('restore_current_blog')) {
                restore_current_blog();
            }
        }
    }

    private function bootstrap(): void
    {
        if (self::$booted) {
            return;
        }

        $wpLoadPath = rtrim($this->wordpressPath, '/') . '/wp-load.php';

        if (!file_exists($wpLoadPath)) {
            throw new \RuntimeException(sprintf(
                'WordPress bootstrap file not found at "%s".',
                $wpLoadPath,
            ));
        }

        require_once $wpLoadPath;

        self::$booted = true;
    }
}
