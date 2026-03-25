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

namespace WpPack\Component\DependencyInjection\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference as SymfonyReference;
use WpPack\Component\DependencyInjection\Reference;

final class ReferenceTest extends TestCase
{
    #[Test]
    public function returnsId(): void
    {
        $ref = new Reference('my.service');

        self::assertSame('my.service', $ref->getId());
    }

    #[Test]
    public function castsToString(): void
    {
        $ref = new Reference('my.service');

        self::assertSame('my.service', (string) $ref);
    }

    #[Test]
    public function convertsToSymfonyReference(): void
    {
        $ref = new Reference('my.service');
        $symfonyRef = $ref->toSymfony();

        self::assertInstanceOf(SymfonyReference::class, $symfonyRef);
        self::assertSame('my.service', (string) $symfonyRef);
    }

    #[Test]
    public function createsFromSymfonyReference(): void
    {
        $symfonyRef = new SymfonyReference('my.service');
        $ref = Reference::fromSymfony($symfonyRef);

        self::assertInstanceOf(Reference::class, $ref);
        self::assertSame('my.service', $ref->getId());
    }

    #[Test]
    public function roundTripsConversion(): void
    {
        $original = new Reference('round.trip');
        $restored = Reference::fromSymfony($original->toSymfony());

        self::assertSame($original->getId(), $restored->getId());
    }
}
