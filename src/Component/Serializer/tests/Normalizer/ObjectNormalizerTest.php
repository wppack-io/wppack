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

namespace WpPack\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Serializer\Exception\NotNormalizableValueException;
use WpPack\Component\Serializer\Normalizer\DateTimeNormalizer;
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WpPack\Component\Serializer\Serializer;
use WpPack\Component\Serializer\Tests\Fixtures\DummyObject;
use WpPack\Component\Serializer\Tests\Fixtures\NestedObject;
use WpPack\Component\Serializer\Tests\Fixtures\NoConstructorObject;
use WpPack\Component\Serializer\Tests\Fixtures\ObjectWithArrayParam;
use WpPack\Component\Serializer\Tests\Fixtures\ObjectWithDatetime;
use WpPack\Component\Serializer\Tests\Fixtures\ObjectWithNullable;
use WpPack\Component\Serializer\Tests\Fixtures\ObjectWithOptional;
use WpPack\Component\Serializer\Tests\Fixtures\ObjectWithUnionType;

#[CoversClass(ObjectNormalizer::class)]
final class ObjectNormalizerTest extends TestCase
{
    private ObjectNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ObjectNormalizer();
    }

    #[Test]
    public function normalizeExtractsPublicProperties(): void
    {
        $object = new DummyObject('test', 42);

        $result = $this->normalizer->normalize($object);

        self::assertSame(['name' => 'test', 'value' => 42], $result);
    }

    #[Test]
    public function normalizeThrowsForNonObject(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected an object, got "string".');

        $this->normalizer->normalize('not-an-object');
    }

    #[Test]
    public function normalizeThrowsForArrayInput(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected an object, got "array".');

        $this->normalizer->normalize(['key' => 'value']);
    }

    #[Test]
    public function denormalizeCreatesObjectFromArray(): void
    {
        $data = ['name' => 'hello', 'value' => 10];

        $result = $this->normalizer->denormalize($data, DummyObject::class);

        self::assertInstanceOf(DummyObject::class, $result);
        self::assertSame('hello', $result->name);
        self::assertSame(10, $result->value);
    }

    #[Test]
    public function denormalizeThrowsForNonArray(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected an array, got "string".');

        $this->normalizer->denormalize('not-an-array', DummyObject::class);
    }

    #[Test]
    public function denormalizeThrowsForNonExistentClass(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Class "NonExistent\FakeClass" does not exist.');

        $this->normalizer->denormalize(['key' => 'value'], 'NonExistent\FakeClass');
    }

    #[Test]
    public function denormalizeObjectWithNoConstructor(): void
    {
        $result = $this->normalizer->denormalize([], NoConstructorObject::class);

        self::assertInstanceOf(NoConstructorObject::class, $result);
        self::assertSame('default', $result->name);
    }

    #[Test]
    public function denormalizeThrowsForMissingRequiredParameter(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Missing required constructor parameter "name"');

        $this->normalizer->denormalize(['value' => 42], DummyObject::class);
    }

    #[Test]
    public function denormalizeUsesDefaultValues(): void
    {
        $result = $this->normalizer->denormalize([], DefaultValueObject::class);

        self::assertSame('default', $result->name);
        self::assertSame(0, $result->value);
    }

    #[Test]
    public function denormalizeWithOptionalParameter(): void
    {
        $result = $this->normalizer->denormalize(['name' => 'test'], ObjectWithOptional::class);

        self::assertSame('test', $result->name);
        self::assertSame('none', $result->description);
    }

    #[Test]
    public function denormalizeWithNullValues(): void
    {
        $result = $this->normalizer->denormalize(['name' => null], ObjectWithNullable::class);

        self::assertInstanceOf(ObjectWithNullable::class, $result);
        self::assertNull($result->name);
        self::assertNull($result->value);
    }

    #[Test]
    public function denormalizeWithScalarValues(): void
    {
        $result = $this->normalizer->denormalize(['name' => 'hello', 'value' => 42], DummyObject::class);

        self::assertSame('hello', $result->name);
        self::assertSame(42, $result->value);
    }

    #[Test]
    public function supportsNormalizationForObjects(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new DummyObject('a', 1)));
        self::assertTrue($this->normalizer->supportsNormalization(new \stdClass()));
        self::assertFalse($this->normalizer->supportsNormalization('string'));
        self::assertFalse($this->normalizer->supportsNormalization(42));
        self::assertFalse($this->normalizer->supportsNormalization(null));
        self::assertFalse($this->normalizer->supportsNormalization(['array']));
    }

    #[Test]
    public function supportsDenormalizationForExistingClasses(): void
    {
        self::assertTrue($this->normalizer->supportsDenormalization(['name' => 'a', 'value' => 1], DummyObject::class));
        self::assertFalse($this->normalizer->supportsDenormalization('not-array', DummyObject::class));
        self::assertFalse($this->normalizer->supportsDenormalization([], 'NonExistent\\Class'));
    }

    #[Test]
    public function normalizeHandlesArrayProperties(): void
    {
        $object = new class (['a', 'b', 'c']) {
            public function __construct(
                /** @var list<string> */
                public readonly array $items,
            ) {}
        };

        $result = $this->normalizer->normalize($object);

        self::assertSame(['items' => ['a', 'b', 'c']], $result);
    }

    #[Test]
    public function normalizeHandlesNullProperties(): void
    {
        $object = new ObjectWithNullable(null);

        $result = $this->normalizer->normalize($object);

        self::assertSame(['name' => null, 'value' => null], $result);
    }

    #[Test]
    public function normalizeNestedObjectWithAwareNormalizer(): void
    {
        // Use the Serializer to wire up normalizer/denormalizer awareness
        $serializer = new Serializer(
            normalizers: [new DateTimeNormalizer(), new ObjectNormalizer()],
        );

        $nested = new NestedObject('parent', new DummyObject('child', 5));
        $result = $serializer->normalize($nested);

        self::assertSame('parent', $result['label']);
        self::assertSame(['name' => 'child', 'value' => 5], $result['child']);
    }

    #[Test]
    public function normalizeObjectWithNestedObjectWithoutAwareness(): void
    {
        // Without normalizer awareness, nested objects are returned as-is
        $nested = new NestedObject('parent', new DummyObject('child', 5));
        $result = $this->normalizer->normalize($nested);

        self::assertSame('parent', $result['label']);
        // Without normalizer awareness, the nested DummyObject is returned as-is (object)
        self::assertInstanceOf(DummyObject::class, $result['child']);
    }

    #[Test]
    public function denormalizeNestedObjectWithAwareDenormalizer(): void
    {
        $serializer = new Serializer(
            normalizers: [new DateTimeNormalizer(), new ObjectNormalizer()],
        );

        $data = ['label' => 'parent', 'child' => ['name' => 'child', 'value' => 5]];
        $result = $serializer->denormalize($data, NestedObject::class);

        self::assertInstanceOf(NestedObject::class, $result);
        self::assertSame('parent', $result->label);
        self::assertInstanceOf(DummyObject::class, $result->child);
        self::assertSame('child', $result->child->name);
    }

    #[Test]
    public function denormalizeWithStringValueForDateTimeProperty(): void
    {
        $serializer = new Serializer(
            normalizers: [new DateTimeNormalizer(), new ObjectNormalizer()],
        );

        $data = ['name' => 'test', 'createdAt' => '2025-01-15T10:30:00+00:00'];
        $result = $serializer->denormalize($data, ObjectWithDatetime::class);

        self::assertInstanceOf(ObjectWithDatetime::class, $result);
        self::assertSame('test', $result->name);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->createdAt);
        self::assertSame('2025-01-15', $result->createdAt->format('Y-m-d'));
    }

    #[Test]
    public function denormalizeWithUnionTypeNullValue(): void
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $data = ['label' => 'test', 'child' => null];
        $result = $serializer->denormalize($data, ObjectWithUnionType::class);

        self::assertInstanceOf(ObjectWithUnionType::class, $result);
        self::assertSame('test', $result->label);
        self::assertNull($result->child);
    }

    #[Test]
    public function denormalizeWithUnionTypeArrayValue(): void
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $data = ['label' => 'test', 'child' => ['name' => 'nested', 'value' => 99]];
        $result = $serializer->denormalize($data, ObjectWithUnionType::class);

        self::assertInstanceOf(ObjectWithUnionType::class, $result);
        self::assertSame('test', $result->label);
        self::assertInstanceOf(DummyObject::class, $result->child);
        self::assertSame('nested', $result->child->name);
    }

    #[Test]
    public function normalizeWithArrayOfNestedObjects(): void
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $object = new class ([new DummyObject('a', 1), new DummyObject('b', 2)]) {
            /** @param list<DummyObject> $items */
            public function __construct(
                public readonly array $items,
            ) {}
        };

        $result = $serializer->normalize($object);

        self::assertIsArray($result['items']);
        self::assertSame(['name' => 'a', 'value' => 1], $result['items'][0]);
        self::assertSame(['name' => 'b', 'value' => 2], $result['items'][1]);
    }

    #[Test]
    public function denormalizeWithScalarStringNotDenormalizable(): void
    {
        // String value for a parameter typed as string (builtin) — should not attempt denormalization
        $result = $this->normalizer->denormalize(['name' => 'hello', 'value' => 42], DummyObject::class);

        self::assertSame('hello', $result->name);
        self::assertSame(42, $result->value);
    }

    #[Test]
    public function denormalizeArrayValueWithoutDenormalizerAwarenessThrowsTypeError(): void
    {
        // Without denormalizer, arrays for non-builtin typed params are passed directly,
        // which causes a TypeError when the constructor expects a typed object
        $this->expectException(\TypeError::class);

        $data = ['label' => 'test', 'child' => ['name' => 'nested', 'value' => 1]];
        $this->normalizer->denormalize($data, NestedObject::class);
    }

    #[Test]
    public function denormalizeArrayParamWithBuiltinType(): void
    {
        // When a constructor param is typed as `array` (builtin), resolveClassName returns null,
        // and the array value is passed through as-is
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $data = ['name' => 'test', 'tags' => ['php', 'wordpress']];
        $result = $serializer->denormalize($data, ObjectWithArrayParam::class);

        self::assertInstanceOf(ObjectWithArrayParam::class, $result);
        self::assertSame('test', $result->name);
        self::assertSame(['php', 'wordpress'], $result->tags);
    }

    #[Test]
    public function denormalizeIntValueWithBuiltinType(): void
    {
        // When a constructor param is typed as `int` (builtin), resolveClassName returns null
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $data = ['name' => 'test', 'value' => 42];
        $result = $serializer->denormalize($data, DummyObject::class);

        self::assertSame('test', $result->name);
        self::assertSame(42, $result->value);
    }

    #[Test]
    public function denormalizeBoolValuePassesThrough(): void
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $object = new class ('test', true) {
            public function __construct(
                public readonly string $name,
                public readonly bool $active,
            ) {}
        };

        $data = ['name' => 'test', 'active' => true];
        $result = $serializer->denormalize($data, $object::class);

        self::assertTrue($result->active);
    }

    #[Test]
    public function resolveClassNameHandlesNoType(): void
    {
        // Constructor parameter with no type declaration
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
        );

        $object = new class ('test', 'value') {
            public function __construct(
                public readonly string $name,
                public $untyped = null,
            ) {}
        };

        $data = ['name' => 'test', 'untyped' => ['some', 'data']];
        $result = $serializer->denormalize($data, $object::class);

        self::assertSame(['some', 'data'], $result->untyped);
    }
}

final readonly class DefaultValueObject
{
    public function __construct(
        public string $name = 'default',
        public int $value = 0,
    ) {}
}
