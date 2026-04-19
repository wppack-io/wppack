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

namespace WPPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Mailer\Address;
use WPPack\Component\Mailer\Exception\InvalidArgumentException;

final class AddressTest extends TestCase
{
    #[Test]
    public function constructWithValidEmail(): void
    {
        $address = new Address('user@example.com');

        self::assertSame('user@example.com', $address->address);
        self::assertSame('', $address->name);
    }

    #[Test]
    public function constructWithNameAndEmail(): void
    {
        $address = new Address('user@example.com', 'John Doe');

        self::assertSame('user@example.com', $address->address);
        self::assertSame('John Doe', $address->name);
    }

    #[Test]
    public function constructParsesNameAngleBracketFormat(): void
    {
        $address = new Address('John Doe <john@example.com>');

        self::assertSame('john@example.com', $address->address);
        self::assertSame('John Doe', $address->name);
    }

    #[Test]
    public function constructRejectsCrlfInAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid control characters');

        new Address("user@example.com\r\nBcc: evil@example.com");
    }

    #[Test]
    public function constructRejectsNewlineInName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid control characters');

        new Address('user@example.com', "John\nDoe");
    }

    #[Test]
    public function constructRejectsNullByteInAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid control characters');

        new Address("user\0@example.com");
    }

    #[Test]
    public function constructRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid email');

        new Address('not-an-email');
    }

    #[Test]
    public function toStringWithoutName(): void
    {
        $address = new Address('user@example.com');

        self::assertSame('user@example.com', $address->toString());
    }

    #[Test]
    public function toStringWithName(): void
    {
        $address = new Address('user@example.com', 'John Doe');

        self::assertSame('"John Doe" <user@example.com>', $address->toString());
    }
}
