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

namespace WpPack\Component\Messenger\Bridge\Sqs\Handler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\MessageBusInterface;
use WpPack\Component\Messenger\Serializer\SerializerInterface;
use WpPack\Component\Messenger\Stamp\ReceivedStamp;
use WpPack\Component\Serializer\Encoder\JsonEncoder;
use WpPack\Component\Site\BlogSwitcher;
use WpPack\Component\Site\BlogSwitcherInterface;

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
        private readonly BlogSwitcherInterface $blogSwitcher = new BlogSwitcher(),
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

        $dispatch = function () use ($envelope, $record): void {
            $existingStamps = $envelope->all();
            $this->messageBus->dispatch(
                $envelope->getMessage(),
                [...$existingStamps, new ReceivedStamp('sqs')],
            );

            $this->logger?->info('Successfully processed SQS message {messageId}', [
                'messageId' => $record['messageId'],
                'messageClass' => $envelope->getMessage()::class,
            ]);
        };

        if ($multisiteStamp !== null) {
            $this->blogSwitcher->runInBlog($multisiteStamp->blogId, $dispatch);
        } else {
            $dispatch();
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
