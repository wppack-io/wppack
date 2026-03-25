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
use WpPack\Component\Serializer\Normalizer\BackedEnumNormalizer;
use WpPack\Component\Serializer\Tests\Fixtures\DummyIntEnum;
use WpPack\Component\Serializer\Tests\Fixtures\DummyStatus;

#[CoversClass(BackedEnumNormalizer::class)]
final class BackedEnumNormalizerTest extends TestCase
{
    private BackedEnumNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new BackedEnumNormalizer();
    }

    #[Test]
    public function normalizeStringEnum(): void
    {
        $result = $this->normalizer->normalize(DummyStatus::Active);

        self::assertSame('active', $result);
    }

    #[Test]
    public function normalizeIntEnum(): void
    {
        $result = $this->normalizer->normalize(DummyIntEnum::High);

        self::assertSame(10, $result);
    }

    #[Test]
    public function normalizeThrowsForNonEnum(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a BackedEnum instance, got "string".');

        $this->normalizer->normalize('not-an-enum');
    }

    #[Test]
    public function normalizeThrowsForObject(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('Expected a BackedEnum instance, got "stdClass".');

        $this->normalizer->normalize(new \stdClass());
    }

    #[Test]
    public function denormalizeStringEnum(): void
    {
        $result = $this->normalizer->denormalize('inactive', DummyStatus::class);

        self::assertSame(DummyStatus::Inactive, $result);
    }

    #[Test]
    public function denormalizeIntEnum(): void
    {
        $result = $this->normalizer->denormalize(1, DummyIntEnum::class);

        self::assertSame(DummyIntEnum::Low, $result);
    }

    #[Test]
    public function denormalizeThrowsForNonEnumType(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('The type "stdClass" is not a BackedEnum.');

        $this->normalizer->denormalize('value', \stdClass::class);
    }

    #[Test]
    public function denormalizeThrowsForStringType(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('is not a BackedEnum');

        $this->normalizer->denormalize('value', 'string');
    }

    #[Test]
    public function supportsNormalization(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(DummyStatus::Active));
        self::assertTrue($this->normalizer->supportsNormalization(DummyIntEnum::Low));
        self::assertFalse($this->normalizer->supportsNormalization('string'));
        self::assertFalse($this->normalizer->supportsNormalization(42));
        self::assertFalse($this->normalizer->supportsNormalization(null));
        self::assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    #[Test]
    public function supportsDenormalization(): void
    {
        self::assertTrue($this->normalizer->supportsDenormalization('active', DummyStatus::class));
        self::assertTrue($this->normalizer->supportsDenormalization(1, DummyIntEnum::class));
        self::assertFalse($this->normalizer->supportsDenormalization('active', \stdClass::class));
        self::assertFalse($this->normalizer->supportsDenormalization('active', 'string'));
    }
}
