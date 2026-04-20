<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use WPPack\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use WPPack\Component\Serializer\Normalizer\DenormalizerInterface;
use WPPack\Component\Serializer\Normalizer\NormalizerAwareInterface;
use WPPack\Component\Serializer\Normalizer\NormalizerAwareTrait;
use WPPack\Component\Serializer\Normalizer\NormalizerInterface;

#[CoversClass(NormalizerAwareTrait::class)]
#[CoversClass(DenormalizerAwareTrait::class)]
final class AwareTraitsTest extends TestCase
{
    #[Test]
    public function normalizerAwareTraitExposesInjectedNormalizer(): void
    {
        $subject = new class implements NormalizerAwareInterface {
            use NormalizerAwareTrait;

            public function inner(): NormalizerInterface
            {
                return $this->normalizer;
            }
        };

        $normalizer = $this->createMock(NormalizerInterface::class);
        $subject->setNormalizer($normalizer);

        self::assertSame($normalizer, $subject->inner());
    }

    #[Test]
    public function denormalizerAwareTraitExposesInjectedDenormalizer(): void
    {
        $subject = new class implements DenormalizerAwareInterface {
            use DenormalizerAwareTrait;

            public function inner(): DenormalizerInterface
            {
                return $this->denormalizer;
            }
        };

        $denormalizer = $this->createMock(DenormalizerInterface::class);
        $subject->setDenormalizer($denormalizer);

        self::assertSame($denormalizer, $subject->inner());
    }
}
