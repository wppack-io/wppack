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

namespace WPPack\Component\Messenger\Serializer;

use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Exception\MessageDecodingFailedException;
use WPPack\Component\Messenger\Exception\MessageEncodingFailedException;
use WPPack\Component\Messenger\Stamp\StampInterface;
use WPPack\Component\Serializer\Encoder\JsonEncoder;
use WPPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WPPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WPPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WPPack\Component\Serializer\Serializer;
use WPPack\Component\Serializer\SerializerInterface as ComponentSerializerInterface;

final class JsonSerializer implements SerializerInterface
{
    private readonly ComponentSerializerInterface $serializer;

    public function __construct(
        ?ComponentSerializerInterface $serializer = null,
    ) {
        $this->serializer = $serializer ?? new Serializer(
            normalizers: [new BackedEnumNormalizer(), new DateTimeNormalizer(), new ObjectNormalizer()],
            encoders: [new JsonEncoder()],
        );
    }

    /**
     * @return array{headers: array<string, mixed>, body: string}
     */
    public function encode(Envelope $envelope): array
    {
        try {
            $message = $envelope->getMessage();
            $stamps = [];

            $allStamps = $envelope->all();
            foreach ($allStamps as $stamp) {
                $stamps[$stamp::class][] = $this->serializer->normalize($stamp);
            }

            return [
                'headers' => [
                    'type' => $message::class,
                    'stamps' => $stamps,
                ],
                'body' => $this->serializer->serialize($message, 'json'),
            ];
        } catch (\Throwable $e) {
            throw new MessageEncodingFailedException(sprintf(
                'Could not encode message of class "%s": %s',
                $envelope->getMessage()::class,
                $e->getMessage(),
            ), previous: $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function decode(array $data): Envelope
    {
        if (!isset($data['headers']['type'], $data['body'])) {
            throw new MessageDecodingFailedException(
                'Missing "headers.type" or "body" in encoded envelope.',
            );
        }

        /** @var class-string $messageClass */
        $messageClass = $data['headers']['type'];
        if (!class_exists($messageClass)) {
            throw new MessageDecodingFailedException(sprintf(
                'Message class "%s" not found.',
                $messageClass,
            ));
        }

        $message = $this->serializer->deserialize($data['body'], $messageClass, 'json');

        $stamps = [];
        foreach ($data['headers']['stamps'] ?? [] as $stampClass => $stampDataList) {
            if (!class_exists($stampClass)) {
                continue;
            }
            foreach ($stampDataList as $stampData) {
                /** @var StampInterface $stamp */
                $stamp = $this->serializer->denormalize($stampData, $stampClass);
                $stamps[] = $stamp;
            }
        }

        return Envelope::wrap($message, $stamps);
    }
}
