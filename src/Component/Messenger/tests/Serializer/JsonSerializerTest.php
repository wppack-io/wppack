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

namespace WpPack\Component\Messenger\Tests\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\MessageDecodingFailedException;
use WpPack\Component\Messenger\Exception\MessageEncodingFailedException;
use WpPack\Component\Messenger\Serializer\JsonSerializer;
use WpPack\Component\Messenger\Stamp\BusNameStamp;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Tests\Fixtures\DummyMessage;
use WpPack\Component\Serializer\SerializerInterface as ComponentSerializerInterface;

#[CoversClass(JsonSerializer::class)]
final class JsonSerializerTest extends TestCase
{
    private JsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
    }

    #[Test]
    public function encodeDecodeRoundTrip(): void
    {
        $message = new DummyMessage('hello', 42);
        $envelope = Envelope::wrap($message, [
            new DelayStamp(1000),
            new BusNameStamp('default'),
        ]);

        $encoded = $this->serializer->encode($envelope);

        self::assertArrayHasKey('headers', $encoded);
        self::assertArrayHasKey('body', $encoded);
        self::assertSame(DummyMessage::class, $encoded['headers']['type']);

        $decoded = $this->serializer->decode($encoded);

        self::assertInstanceOf(DummyMessage::class, $decoded->getMessage());
        /** @var DummyMessage $decodedMessage */
        $decodedMessage = $decoded->getMessage();
        self::assertSame('hello', $decodedMessage->content);
        self::assertSame(42, $decodedMessage->userId);

        self::assertNotNull($decoded->last(DelayStamp::class));
        self::assertSame(1000, $decoded->last(DelayStamp::class)->delayInMilliseconds);
        self::assertNotNull($decoded->last(BusNameStamp::class));
        self::assertSame('default', $decoded->last(BusNameStamp::class)->busName);
    }

    #[Test]
    public function decodeThrowsOnMissingType(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Missing "headers.type" or "body"');

        $this->serializer->decode(['headers' => [], 'body' => '{}']);
    }

    #[Test]
    public function decodeThrowsOnMissingBody(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode(['headers' => ['type' => 'SomeClass']]);
    }

    #[Test]
    public function decodeThrowsOnNonExistentClass(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('not found');

        $this->serializer->decode([
            'headers' => ['type' => 'NonExistent\\FakeClass'],
            'body' => '{}',
        ]);
    }

    #[Test]
    public function encodeProducesValidJson(): void
    {
        $message = new DummyMessage('test', 1);
        $envelope = Envelope::wrap($message);

        $encoded = $this->serializer->encode($envelope);

        $decoded = json_decode($encoded['body'], true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('test', $decoded['content']);
        self::assertSame(1, $decoded['userId']);
    }

    #[Test]
    public function skipsUnknownStampClassesDuringDecode(): void
    {
        $data = [
            'headers' => [
                'type' => DummyMessage::class,
                'stamps' => [
                    'NonExistent\\StampClass' => [['value' => 'test']],
                    BusNameStamp::class => [['busName' => 'mybus']],
                ],
            ],
            'body' => json_encode(['content' => 'hello', 'userId' => 1]),
        ];

        $decoded = $this->serializer->decode($data);

        self::assertNotNull($decoded->last(BusNameStamp::class));
        self::assertSame('mybus', $decoded->last(BusNameStamp::class)->busName);
    }

    #[Test]
    public function encodeThrowsMessageEncodingFailedExceptionOnSerializerError(): void
    {
        $failingSerializer = new class implements ComponentSerializerInterface {
            public function serialize(mixed $data, string $format, array $context = []): string
            {
                throw new \RuntimeException('Serialization failed');
            }

            public function deserialize(string $data, string $type, string $format, array $context = []): object
            {
                return new \stdClass();
            }

            public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null
            {
                return [];
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
            {
                return new \stdClass();
            }
        };

        $serializer = new JsonSerializer($failingSerializer);
        $envelope = Envelope::wrap(new DummyMessage('test', 1));

        $this->expectException(MessageEncodingFailedException::class);
        $this->expectExceptionMessage('Could not encode message');

        $serializer->encode($envelope);
    }

    #[Test]
    public function encodeWrapsOriginalExceptionAsPrevious(): void
    {
        $original = new \RuntimeException('Original error');
        $failingSerializer = new class ($original) implements ComponentSerializerInterface {
            public function __construct(private readonly \Throwable $exception) {}

            public function serialize(mixed $data, string $format, array $context = []): string
            {
                throw $this->exception;
            }

            public function deserialize(string $data, string $type, string $format, array $context = []): object
            {
                return new \stdClass();
            }

            public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null
            {
                return [];
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
            {
                return new \stdClass();
            }
        };

        $serializer = new JsonSerializer($failingSerializer);
        $envelope = Envelope::wrap(new DummyMessage('test', 1));

        try {
            $serializer->encode($envelope);
            self::fail('Expected MessageEncodingFailedException');
        } catch (MessageEncodingFailedException $e) {
            self::assertSame($original, $e->getPrevious());
            self::assertStringContainsString(DummyMessage::class, $e->getMessage());
        }
    }

    #[Test]
    public function decodeWithoutStampsHeader(): void
    {
        $data = [
            'headers' => [
                'type' => DummyMessage::class,
            ],
            'body' => json_encode(['content' => 'no-stamps', 'userId' => 99]),
        ];

        $decoded = $this->serializer->decode($data);

        self::assertInstanceOf(DummyMessage::class, $decoded->getMessage());
        /** @var DummyMessage $decodedMessage */
        $decodedMessage = $decoded->getMessage();
        self::assertSame('no-stamps', $decodedMessage->content);
        self::assertSame(99, $decodedMessage->userId);
        self::assertSame([], $decoded->all());
    }

    #[Test]
    public function decodeWithMultipleStampsOfSameType(): void
    {
        $data = [
            'headers' => [
                'type' => DummyMessage::class,
                'stamps' => [
                    DelayStamp::class => [
                        ['delayInMilliseconds' => 100],
                        ['delayInMilliseconds' => 200],
                    ],
                ],
            ],
            'body' => json_encode(['content' => 'multi', 'userId' => 1]),
        ];

        $decoded = $this->serializer->decode($data);

        $stamps = $decoded->all(DelayStamp::class);
        self::assertCount(2, $stamps);
        self::assertSame(100, $stamps[0]->delayInMilliseconds);
        self::assertSame(200, $stamps[1]->delayInMilliseconds);
    }

    #[Test]
    public function constructorWithCustomSerializer(): void
    {
        $customSerializer = $this->createMock(ComponentSerializerInterface::class);
        $customSerializer->method('normalize')->willReturn([]);
        $customSerializer->method('serialize')->willReturn('{"content":"custom","userId":0}');

        $serializer = new JsonSerializer($customSerializer);
        $envelope = Envelope::wrap(new DummyMessage('custom', 0));
        $encoded = $serializer->encode($envelope);

        self::assertSame('{"content":"custom","userId":0}', $encoded['body']);
    }

    #[Test]
    public function encodeThrowsWhenNormalizeFails(): void
    {
        $failingSerializer = new class implements ComponentSerializerInterface {
            public function serialize(mixed $data, string $format, array $context = []): string
            {
                return '{}';
            }

            public function deserialize(string $data, string $type, string $format, array $context = []): object
            {
                return new \stdClass();
            }

            public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null
            {
                throw new \RuntimeException('Normalize failed');
            }

            public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
            {
                return new \stdClass();
            }
        };

        $serializer = new JsonSerializer($failingSerializer);
        $envelope = Envelope::wrap(new DummyMessage('test', 1), [new DelayStamp(100)]);

        $this->expectException(MessageEncodingFailedException::class);

        $serializer->encode($envelope);
    }

    #[Test]
    public function decodeThrowsOnMissingHeaders(): void
    {
        $this->expectException(MessageDecodingFailedException::class);
        $this->expectExceptionMessage('Missing "headers.type" or "body"');

        $this->serializer->decode(['body' => '{}']);
    }

    #[Test]
    public function decodeThrowsOnEmptyArray(): void
    {
        $this->expectException(MessageDecodingFailedException::class);

        $this->serializer->decode([]);
    }
}
