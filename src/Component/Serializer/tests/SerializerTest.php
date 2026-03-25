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

namespace WpPack\Component\Serializer\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Serializer\Encoder\JsonEncoder;
use WpPack\Component\Serializer\Exception\InvalidArgumentException;
use WpPack\Component\Serializer\Exception\NotNormalizableValueException;
use WpPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WpPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WpPack\Component\Serializer\Serializer;
use WpPack\Component\Serializer\Tests\Fixtures\DummyObject;
use WpPack\Component\Serializer\Tests\Fixtures\DummyStatus;
use WpPack\Component\Serializer\Tests\Fixtures\NestedObject;
use WpPack\Component\Serializer\Tests\Fixtures\ObjectWithDatetime;

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
    public function denormalizeNestedObjects(): void
    {
        $data = ['label' => 'parent', 'child' => ['name' => 'child', 'value' => 5]];

        $result = $this->serializer->denormalize($data, NestedObject::class);

        self::assertInstanceOf(NestedObject::class, $result);
        self::assertSame('parent', $result->label);
        self::assertInstanceOf(DummyObject::class, $result->child);
        self::assertSame('child', $result->child->name);
        self::assertSame(5, $result->child->value);
    }

    #[Test]
    public function serializeAndDeserializeNestedObjects(): void
    {
        $object = new NestedObject('parent', new DummyObject('child', 5));

        $json = $this->serializer->serialize($object, 'json');
        $restored = $this->serializer->deserialize($json, NestedObject::class, 'json');

        self::assertInstanceOf(NestedObject::class, $restored);
        self::assertSame('parent', $restored->label);
        self::assertInstanceOf(DummyObject::class, $restored->child);
        self::assertSame('child', $restored->child->name);
        self::assertSame(5, $restored->child->value);
    }

    #[Test]
    public function normalizeScalarsPassThrough(): void
    {
        self::assertNull($this->serializer->normalize(null));
        self::assertSame('string', $this->serializer->normalize('string'));
        self::assertSame(42, $this->serializer->normalize(42));
        self::assertTrue($this->serializer->normalize(true));
        self::assertSame(3.14, $this->serializer->normalize(3.14));
    }

    #[Test]
    public function normalizeArrayOfScalars(): void
    {
        $result = $this->serializer->normalize(['a', 'b', 'c']);

        self::assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function normalizeArrayOfObjects(): void
    {
        $result = $this->serializer->normalize([
            new DummyObject('first', 1),
            new DummyObject('second', 2),
        ]);

        self::assertSame([
            ['name' => 'first', 'value' => 1],
            ['name' => 'second', 'value' => 2],
        ], $result);
    }

    #[Test]
    public function normalizeAssociativeArray(): void
    {
        $result = $this->serializer->normalize(['key1' => 'value1', 'key2' => 42]);

        self::assertSame(['key1' => 'value1', 'key2' => 42], $result);
    }

    #[Test]
    public function normalizeThrowsForUnsupportedObjectType(): void
    {
        // Serializer without any normalizers should throw for objects
        $serializer = new Serializer();

        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('no supporting normalizer found');

        $serializer->normalize(new \stdClass());
    }

    #[Test]
    public function denormalizeThrowsForUnsupportedType(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('no supporting denormalizer found');

        $this->serializer->denormalize([], 'NonExistent\\FakeClass');
    }

    #[Test]
    public function supportsNormalizationForScalarsAndNull(): void
    {
        self::assertTrue($this->serializer->supportsNormalization(null));
        self::assertTrue($this->serializer->supportsNormalization('string'));
        self::assertTrue($this->serializer->supportsNormalization(42));
        self::assertTrue($this->serializer->supportsNormalization(3.14));
        self::assertTrue($this->serializer->supportsNormalization(true));
        self::assertTrue($this->serializer->supportsNormalization(false));
    }

    #[Test]
    public function supportsNormalizationForArrays(): void
    {
        self::assertTrue($this->serializer->supportsNormalization([]));
        self::assertTrue($this->serializer->supportsNormalization(['a', 'b']));
    }

    #[Test]
    public function supportsNormalizationForObjects(): void
    {
        self::assertTrue($this->serializer->supportsNormalization(new DummyObject('a', 1)));
        self::assertTrue($this->serializer->supportsNormalization(DummyStatus::Active));
        self::assertTrue($this->serializer->supportsNormalization(new \DateTimeImmutable()));
    }

    #[Test]
    public function supportsNormalizationReturnsFalseForUnsupportedObject(): void
    {
        $serializer = new Serializer();

        self::assertFalse($serializer->supportsNormalization(new \stdClass()));
    }

    #[Test]
    public function supportsDenormalizationWithSupportedType(): void
    {
        self::assertTrue($this->serializer->supportsDenormalization(
            ['name' => 'test', 'value' => 1],
            DummyObject::class,
        ));
        self::assertTrue($this->serializer->supportsDenormalization(
            'active',
            DummyStatus::class,
        ));
        self::assertTrue($this->serializer->supportsDenormalization(
            '2025-01-01',
            \DateTimeImmutable::class,
        ));
    }

    #[Test]
    public function supportsDenormalizationReturnsFalseForUnsupportedType(): void
    {
        self::assertFalse($this->serializer->supportsDenormalization(
            [],
            'NonExistent\\FakeClass',
        ));
    }

    #[Test]
    public function supportsDenormalizationReturnsFalseWithNoNormalizers(): void
    {
        $serializer = new Serializer();

        self::assertFalse($serializer->supportsDenormalization([], DummyObject::class));
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

    #[Test]
    public function serializeThrowsForUnsupportedFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No encoder found for format "xml".');

        $this->serializer->serialize(new DummyObject('test', 1), 'xml');
    }

    #[Test]
    public function deserializeThrowsForUnsupportedFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No decoder found for format "xml".');

        $this->serializer->deserialize('<data/>', DummyObject::class, 'xml');
    }

    #[Test]
    public function serializerWithNoEncoders(): void
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No encoder found');

        $serializer->serialize(new DummyObject('test', 1), 'json');
    }

    #[Test]
    public function serializerWithNoNormalizersAndNoEncoders(): void
    {
        $serializer = new Serializer();

        // Scalar values can still be normalized
        self::assertSame('hello', $serializer->normalize('hello'));
        self::assertSame(42, $serializer->normalize(42));
        self::assertNull($serializer->normalize(null));
    }

    #[Test]
    public function serializeObjectWithDateTimeIntegration(): void
    {
        $object = new ObjectWithDatetime('test', new \DateTimeImmutable('2025-01-15T10:30:00+00:00'));

        $json = $this->serializer->serialize($object, 'json');

        self::assertStringContainsString('"name":"test"', $json);
        self::assertStringContainsString('"createdAt":"2025-01-15T10:30:00+00:00"', $json);
    }

    #[Test]
    public function deserializeObjectWithDateTimeIntegration(): void
    {
        $json = '{"name":"test","createdAt":"2025-01-15T10:30:00+00:00"}';

        $result = $this->serializer->deserialize($json, ObjectWithDatetime::class, 'json');

        self::assertInstanceOf(ObjectWithDatetime::class, $result);
        self::assertSame('test', $result->name);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->createdAt);
        self::assertSame('2025-01-15', $result->createdAt->format('Y-m-d'));
    }

    #[Test]
    public function constructorSetsNormalizerAwareness(): void
    {
        // The fact that nested normalization/denormalization works proves
        // that NormalizerAware and DenormalizerAware are set correctly during construction
        $nested = new NestedObject('p', new DummyObject('c', 1));

        $json = $this->serializer->serialize($nested, 'json');
        $restored = $this->serializer->deserialize($json, NestedObject::class, 'json');

        self::assertInstanceOf(DummyObject::class, $restored->child);
        self::assertSame('c', $restored->child->name);
    }
}
