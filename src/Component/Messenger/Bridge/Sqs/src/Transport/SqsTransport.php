<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Bridge\Sqs\Transport;

use AsyncAws\Sqs\Input\SendMessageRequest;
use AsyncAws\Sqs\SqsClient;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\TransportException;
use WpPack\Component\Messenger\Serializer\SerializerInterface;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Stamp\SentStamp;
use WpPack\Component\Messenger\Transport\TransportInterface;

final class SqsTransport implements TransportInterface
{
    private const int MAX_SQS_DELAY_SECONDS = 900;

    public function __construct(
        private readonly SqsClient $sqsClient,
        private readonly SerializerInterface $serializer,
        private readonly string $queueUrl,
        private readonly string $name = 'sqs',
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
            'MessageBody' => json_encode($encodedEnvelope, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
        ];

        $delayStamp = $envelope->last(DelayStamp::class);

        if ($delayStamp !== null) {
            $delaySeconds = max(0, (int) ceil($delayStamp->delayInMilliseconds / 1000));
            $input['DelaySeconds'] = min($delaySeconds, self::MAX_SQS_DELAY_SECONDS);
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
