<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Serializer;

use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\MessageDecodingFailedException;
use WpPack\Component\Messenger\Stamp\StampInterface;

final class JsonSerializer implements SerializerInterface
{
    /**
     * @return array{headers: array<string, mixed>, body: string}
     */
    public function encode(Envelope $envelope): array
    {
        try {
            $message = $envelope->getMessage();
            $stamps = [];

            $allStamps = $envelope->all(); // @phpstan-ignore argument.templateType
            foreach ($allStamps as $stamp) {
                $stamps[$stamp::class][] = $this->normalizeStamp($stamp);
            }

            return [
                'headers' => [
                    'type' => $message::class,
                    'stamps' => $stamps,
                ],
                'body' => json_encode(
                    $this->normalizeMessage($message),
                    \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE,
                ),
            ];
        } catch (\JsonException $e) {
            throw new MessageDecodingFailedException(sprintf(
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

        /** @var array<string, mixed> $messageData */
        $messageData = json_decode($data['body'], true, 512, \JSON_THROW_ON_ERROR);
        $message = $this->denormalizeMessage($messageClass, $messageData);

        $stamps = [];
        foreach ($data['headers']['stamps'] ?? [] as $stampClass => $stampDataList) {
            if (!class_exists($stampClass)) {
                continue;
            }
            foreach ($stampDataList as $stampData) {
                $stamps[] = $this->denormalizeStamp($stampClass, $stampData);
            }
        }

        return Envelope::wrap($message, $stamps);
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
     * @param class-string $class
     * @param array<string, mixed> $data
     */
    private function denormalizeMessage(string $class, array $data): object
    {
        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();
        if ($constructor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $args[$name] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
            }
        }

        return $ref->newInstanceArgs($args);
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

    /**
     * @param class-string<StampInterface> $class
     * @param array<string, mixed> $data
     */
    private function denormalizeStamp(string $class, array $data): StampInterface
    {
        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();
        if ($constructor === null) {
            /** @var StampInterface */
            return $ref->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $args[$name] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[$name] = $param->getDefaultValue();
            }
        }

        /** @var StampInterface */
        return $ref->newInstanceArgs($args);
    }
}
