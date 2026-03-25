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

#[CoversClass(DateTimeNormalizer::class)]
final class DateTimeNormalizerTest extends TestCase
{
    private DateTimeNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DateTimeNormalizer();
    }

    #[Test]
    public function normalizeProducesAtomString(): void
    {
        $date = new \DateTimeImmutable('2025-01-15T10:30:00+00:00');

        $result = $this->normalizer->normalize($date);

        self::assertSame('2025-01-15T10:30:00+00:00', $result);
    }

    #[Test]
    public function normalizeMutableDateTime(): void
    {
        $date = new \DateTime('2025-06-01T08:00:00+09:00');

        $result = $this->normalizer->normalize($date);

        self::assertSame('2025-06-01T08:00:00+09:00', $result);
    }

    #[Test]
    public function normalizeThrowsForNonDateTimeInterface(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a DateTimeInterface instance, got "string".');

        $this->normalizer->normalize('2025-01-15');
    }

    #[Test]
    public function normalizeThrowsForIntValue(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a DateTimeInterface instance, got "int".');

        $this->normalizer->normalize(12345);
    }

    #[Test]
    public function denormalizeCreatesDateTimeImmutable(): void
    {
        $result = $this->normalizer->denormalize('2025-01-15T10:30:00+00:00', \DateTimeImmutable::class);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2025-01-15', $result->format('Y-m-d'));
    }

    #[Test]
    public function denormalizeCreatesDateTime(): void
    {
        $result = $this->normalizer->denormalize('2025-01-15T10:30:00+00:00', \DateTime::class);

        self::assertInstanceOf(\DateTime::class, $result);
        self::assertSame('2025-01-15', $result->format('Y-m-d'));
    }

    #[Test]
    public function denormalizeDateTimeInterfaceReturnImmutable(): void
    {
        $result = $this->normalizer->denormalize('2025-01-15T10:30:00+00:00', \DateTimeInterface::class);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    #[Test]
    public function denormalizeDateTimeImmutableFallsBackToConstructor(): void
    {
        // A non-ATOM string that fails createFromFormat but works with constructor
        $result = $this->normalizer->denormalize('2025-01-15', \DateTimeImmutable::class);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2025-01-15', $result->format('Y-m-d'));
    }

    #[Test]
    public function denormalizeDateTimeFallsBackToConstructor(): void
    {
        // A non-ATOM string that fails createFromFormat but works with constructor
        $result = $this->normalizer->denormalize('2025-01-15', \DateTime::class);

        self::assertInstanceOf(\DateTime::class, $result);
        self::assertSame('2025-01-15', $result->format('Y-m-d'));
    }

    #[Test]
    public function denormalizeDateTimeInterfaceFallsBackToConstructor(): void
    {
        $result = $this->normalizer->denormalize('next Monday', \DateTimeInterface::class);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
    }

    #[Test]
    public function denormalizeThrowsForInvalidDateString(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Failed to denormalize');

        $this->normalizer->denormalize('not-a-date-!!!', \DateTimeImmutable::class);
    }

    #[Test]
    public function denormalizeThrowsForInvalidDateStringDateTime(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Failed to denormalize');

        $this->normalizer->denormalize('not-a-date-!!!', \DateTime::class);
    }

    #[Test]
    public function denormalizeThrowsForUnsupportedType(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Unsupported datetime type "stdClass".');

        $this->normalizer->denormalize('2025-01-15', \stdClass::class);
    }

    #[Test]
    public function supportsNormalization(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new \DateTimeImmutable()));
        self::assertTrue($this->normalizer->supportsNormalization(new \DateTime()));
        self::assertFalse($this->normalizer->supportsNormalization('string'));
        self::assertFalse($this->normalizer->supportsNormalization(42));
        self::assertFalse($this->normalizer->supportsNormalization(null));
    }

    #[Test]
    public function supportsDenormalization(): void
    {
        self::assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTimeImmutable::class));
        self::assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTime::class));
        self::assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTimeInterface::class));
        self::assertFalse($this->normalizer->supportsDenormalization(123, \DateTimeImmutable::class));
        self::assertFalse($this->normalizer->supportsDenormalization('2025-01-01', \stdClass::class));
        self::assertFalse($this->normalizer->supportsDenormalization(null, \DateTimeImmutable::class));
    }

    #[Test]
    public function customFormatViaContext(): void
    {
        $date = new \DateTimeImmutable('2025-01-15T10:30:00+00:00');
        $context = [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'];

        $normalized = $this->normalizer->normalize($date, null, $context);
        self::assertSame('2025-01-15', $normalized);

        $denormalized = $this->normalizer->denormalize('2025-01-15', \DateTimeImmutable::class, null, $context);
        self::assertSame('2025-01-15', $denormalized->format('Y-m-d'));
    }

    #[Test]
    public function customFormatDenormalizeDateTimeType(): void
    {
        $context = [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s'];

        $result = $this->normalizer->denormalize('2025-06-15 14:30:00', \DateTime::class, null, $context);

        self::assertInstanceOf(\DateTime::class, $result);
        self::assertSame('2025-06-15 14:30:00', $result->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function customFormatDenormalizeDateTimeInterfaceType(): void
    {
        $context = [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d H:i:s'];

        $result = $this->normalizer->denormalize('2025-06-15 14:30:00', \DateTimeInterface::class, null, $context);

        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2025-06-15 14:30:00', $result->format('Y-m-d H:i:s'));
    }
}
