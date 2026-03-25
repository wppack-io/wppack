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

namespace WpPack\Component\Mailer\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Header\Headers;

final class HeadersTest extends TestCase
{
    #[Test]
    public function addAndGetHeader(): void
    {
        $headers = new Headers();
        $headers->add('X-Custom', 'value1');

        self::assertSame('value1', $headers->get('X-Custom'));
    }

    #[Test]
    public function addMultipleValuesForSameHeader(): void
    {
        $headers = new Headers();
        $headers->add('X-Custom', 'value1');
        $headers->add('X-Custom', 'value2');

        $all = $headers->all();
        self::assertCount(2, $all['X-Custom']);
        self::assertSame('value1', $all['X-Custom'][0]);
        self::assertSame('value2', $all['X-Custom'][1]);
    }

    #[Test]
    public function getNonExistentHeader(): void
    {
        $headers = new Headers();

        self::assertNull($headers->get('X-Missing'));
    }

    #[Test]
    public function hasHeader(): void
    {
        $headers = new Headers();
        $headers->add('X-Exists', 'value');

        self::assertTrue($headers->has('X-Exists'));
        self::assertFalse($headers->has('X-Missing'));
    }

    #[Test]
    public function removeHeader(): void
    {
        $headers = new Headers();
        $headers->add('X-Remove', 'value');
        $headers->remove('X-Remove');

        self::assertFalse($headers->has('X-Remove'));
    }

    #[Test]
    public function rejectsCrlfInHeaderName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid control characters');

        $headers = new Headers();
        $headers->add("X-Bad\r\n", 'value');
    }

    #[Test]
    public function rejectsCrlfInHeaderValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid control characters');

        $headers = new Headers();
        $headers->add('X-Custom', "value\r\nEvil: header");
    }

    #[Test]
    public function rejectsNullByteInHeader(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $headers = new Headers();
        $headers->add('X-Custom', "value\0evil");
    }
}
