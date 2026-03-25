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

namespace WpPack\Component\Messenger\Bridge\Sqs\Transport;

use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\TransportException;
use WpPack\Component\Messenger\Serializer\SerializerInterface;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Stamp\SentStamp;
use WpPack\Component\Messenger\Transport\TransportInterface;
use WpPack\Component\Serializer\Encoder\JsonEncoder;

final class SqsTransport implements TransportInterface
{
    private const MAX_SQS_DELAY_SECONDS = 900;

    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly SerializerInterface $serializer,
        private readonly string $queueUrl,
        private readonly string $name = 'sqs',
        private readonly ?LoggerInterface $logger = null,
        private readonly JsonEncoder $jsonEncoder = new JsonEncoder(),
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function send(Envelope $envelope): Envelope
    {
        $encodedEnvelope = $this->serializer->encode($envelope);

        $input = [
            'QueueUrl' => $this->queueUrl,
            'MessageBody' => $this->jsonEncoder->encode($encodedEnvelope, 'json'),
        ];

        $delayStamp = $envelope->last(DelayStamp::class);

        if ($delayStamp !== null) {
            $delaySeconds = max(0, (int) ceil($delayStamp->delayInMilliseconds / 1000));
            $clamped = min($delaySeconds, self::MAX_SQS_DELAY_SECONDS);

            if ($clamped < $delaySeconds) {
                $this->logger?->warning(
                    'SQS delay clamped from {requested}s to {max}s (SQS maximum).',
                    ['requested' => $delaySeconds, 'max' => self::MAX_SQS_DELAY_SECONDS],
                );
            }

            $input['DelaySeconds'] = $clamped;
        }

        try {
            $result = $this->sqsClient->sendMessage(new SendMessageRequest($input));
            $result->getMessageId();
        } catch (\Throwable $e) {
            throw new TransportException(
                sprintf('Failed to send message to SQS queue "%s": %s', $this->queueUrl, $e->getMessage()),
                previous: $e,
            );
        }

        return $envelope->with(new SentStamp($this->name));
    }
}
