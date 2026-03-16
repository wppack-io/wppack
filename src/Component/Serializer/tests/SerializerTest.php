<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Serializer\Encoder\JsonEncoder;
use WpPack\Component\Serializer\Exception\NotNormalizableValueException;
use WpPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WpPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WpPack\Component\Serializer\Serializer;
use WpPack\Component\Serializer\Tests\Fixtures\DummyObject;
use WpPack\Component\Serializer\Tests\Fixtures\DummyStatus;
use WpPack\Component\Serializer\Tests\Fixtures\NestedObject;

#[CoversClass(Serializer::class)]
final class SerializerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer(
            normalizers: [
                new BackedEnumNormalizer(),
                new DateTimeNormalizer(),
                new ObjectNormalizer(),
            ],
            encoders: [new JsonEncoder()],
        );
    }

    #[Test]
    public function serializeAndDeserializeObject(): void
    {
        $object = new DummyObject('test', 42);

        $json = $this->serializer->serialize($object, 'json');
        self::assertSame('{"name":"test","value":42}', $json);

        $restored = $this->serializer->deserialize($json, DummyObject::class, 'json');
        self::assertInstanceOf(DummyObject::class, $restored);
        self::assertSame('test', $restored->name);
        self::assertSame(42, $restored->value);
    }

    #[Test]
    public function normalizeAndDenormalize(): void
    {
        $object = new DummyObject('hello', 10);

        $normalized = $this->serializer->normalize($object);
        self::assertSame(['name' => 'hello', 'value' => 10], $normalized);

        $denormalized = $this->serializer->denormalize($normalized, DummyObject::class);
        self::assertSame('hello', $denormalized->name);
        self::assertSame(10, $denormalized->value);
    }

    #[Test]
    public function normalizeNestedObjects(): void
    {
        $object = new NestedObject('parent', new DummyObject('child', 5));

        $normalized = $this->serializer->normalize($object);

        self::assertSame('parent', $normalized['label']);
        self::assertSame(['name' => 'child', 'value' => 5], $normalized['child']);
    }

    #[Test]
    public function normalizeScalarsPassThrough(): void
    {
        self::assertNull($this->serializer->normalize(null));
        self::assertSame('string', $this->serializer->normalize('string'));
        self::assertSame(42, $this->serializer->normalize(42));
        self::assertTrue($this->serializer->normalize(true));
    }

    #[Test]
    public function normalizeArrayOfScalars(): void
    {
        $result = $this->serializer->normalize(['a', 'b', 'c']);

        self::assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function denormalizeThrowsForUnsupportedType(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('no supporting denormalizer found');

        $this->serializer->denormalize([], 'NonExistent\\FakeClass');
    }

    #[Test]
    public function backedEnumNormalizerIntegration(): void
    {
        $normalized = $this->serializer->normalize(DummyStatus::Active);
        self::assertSame('active', $normalized);

        $denormalized = $this->serializer->denormalize('active', DummyStatus::class);
        self::assertSame(DummyStatus::Active, $denormalized);
    }

    #[Test]
    public function dateTimeNormalizerIntegration(): void
    {
        $date = new \DateTimeImmutable('2025-06-15T12:00:00+00:00');

        $normalized = $this->serializer->normalize($date);
        self::assertSame('2025-06-15T12:00:00+00:00', $normalized);

        $denormalized = $this->serializer->denormalize('2025-06-15T12:00:00+00:00', \DateTimeImmutable::class);
        self::assertSame('2025-06-15', $denormalized->format('Y-m-d'));
    }
}
