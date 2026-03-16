<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Serializer\Exception\NotNormalizableValueException;
use WpPack\Component\Serializer\Normalizer\ObjectNormalizer;
use WpPack\Component\Serializer\Tests\Fixtures\DummyObject;

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
    public function denormalizeCreatesObjectFromArray(): void
    {
        $data = ['name' => 'hello', 'value' => 10];

        $result = $this->normalizer->denormalize($data, DummyObject::class);

        self::assertInstanceOf(DummyObject::class, $result);
        self::assertSame('hello', $result->name);
        self::assertSame(10, $result->value);
    }

    #[Test]
    public function supportsNormalizationForObjects(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new DummyObject('a', 1)));
        self::assertFalse($this->normalizer->supportsNormalization('string'));
        self::assertFalse($this->normalizer->supportsNormalization(42));
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
}

final readonly class DefaultValueObject
{
    public function __construct(
        public string $name = 'default',
        public int $value = 0,
    ) {}
}
