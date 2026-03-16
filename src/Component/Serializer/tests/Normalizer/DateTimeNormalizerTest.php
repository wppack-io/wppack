<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
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
    public function supportsNormalization(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(new \DateTimeImmutable()));
        self::assertTrue($this->normalizer->supportsNormalization(new \DateTime()));
        self::assertFalse($this->normalizer->supportsNormalization('string'));
    }

    #[Test]
    public function supportsDenormalization(): void
    {
        self::assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTimeImmutable::class));
        self::assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTime::class));
        self::assertTrue($this->normalizer->supportsDenormalization('2025-01-01', \DateTimeInterface::class));
        self::assertFalse($this->normalizer->supportsDenormalization(123, \DateTimeImmutable::class));
        self::assertFalse($this->normalizer->supportsDenormalization('2025-01-01', \stdClass::class));
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
}
