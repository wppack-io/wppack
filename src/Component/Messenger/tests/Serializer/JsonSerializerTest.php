<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Tests\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\MessageDecodingFailedException;
use WpPack\Component\Messenger\Serializer\JsonSerializer;
use WpPack\Component\Messenger\Stamp\BusNameStamp;
use WpPack\Component\Messenger\Stamp\DelayStamp;
use WpPack\Component\Messenger\Tests\Fixtures\DummyMessage;

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
}
