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

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\HeaderBag;

final class HeaderBagTest extends TestCase
{
    #[Test]
    public function constructorNormalizesKeysToLowercase(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'text/html', 'X-Custom' => 'value']);

        self::assertTrue($bag->has('content-type'));
        self::assertTrue($bag->has('x-custom'));
    }

    #[Test]
    public function getReturnsFirstValue(): void
    {
        $bag = new HeaderBag(['accept' => ['text/html', 'application/json']]);

        self::assertSame('text/html', $bag->get('accept'));
    }

    #[Test]
    public function getReturnsDefaultForMissingKey(): void
    {
        $bag = new HeaderBag();

        self::assertNull($bag->get('missing'));
        self::assertSame('default', $bag->get('missing', 'default'));
    }

    #[Test]
    public function hasIsCaseInsensitive(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'text/html']);

        self::assertTrue($bag->has('content-type'));
        self::assertTrue($bag->has('Content-Type'));
        self::assertTrue($bag->has('CONTENT-TYPE'));
        self::assertFalse($bag->has('x-missing'));
    }

    #[Test]
    public function setAcceptsStringValue(): void
    {
        $bag = new HeaderBag();
        $bag->set('Content-Type', 'application/json');

        self::assertSame('application/json', $bag->get('content-type'));
    }

    #[Test]
    public function setAcceptsArrayValue(): void
    {
        $bag = new HeaderBag();
        $bag->set('Accept', ['text/html', 'application/json']);

        self::assertSame('text/html', $bag->get('accept'));
        self::assertSame(['text/html', 'application/json'], $bag->all()['accept']);
    }

    #[Test]
    public function allReturnsAllHeaders(): void
    {
        $bag = new HeaderBag(['Host' => 'example.com', 'Accept' => 'text/html']);
        $all = $bag->all();

        self::assertArrayHasKey('host', $all);
        self::assertArrayHasKey('accept', $all);
        self::assertSame(['example.com'], $all['host']);
        self::assertSame(['text/html'], $all['accept']);
    }

    #[Test]
    public function keysReturnsLowercaseKeys(): void
    {
        $bag = new HeaderBag(['Content-Type' => 'text/html', 'X-Custom' => 'value']);

        $keys = $bag->keys();

        self::assertContains('content-type', $keys);
        self::assertContains('x-custom', $keys);
    }

    #[Test]
    public function countReturnsHeaderCount(): void
    {
        $bag = new HeaderBag(['Host' => 'example.com', 'Accept' => 'text/html']);

        self::assertSame(2, $bag->count());
    }

    #[Test]
    public function getDateParsesRfc7231Date(): void
    {
        $dateString = 'Wed, 21 Oct 2015 07:28:00 GMT';
        $bag = new HeaderBag(['date' => $dateString]);

        $date = $bag->getDate('date');

        self::assertInstanceOf(\DateTimeImmutable::class, $date);
        self::assertSame('2015', $date->format('Y'));
        self::assertSame('10', $date->format('m'));
        self::assertSame('21', $date->format('d'));
    }

    #[Test]
    public function getDateReturnsDefaultForMissingKey(): void
    {
        $bag = new HeaderBag();
        $default = new \DateTimeImmutable('2020-01-01');

        self::assertNull($bag->getDate('missing'));
        self::assertSame($default, $bag->getDate('missing', $default));
    }

    #[Test]
    public function getDateReturnsDefaultForInvalidDate(): void
    {
        $bag = new HeaderBag(['date' => 'not-a-date']);
        $default = new \DateTimeImmutable('2020-01-01');

        self::assertSame($default, $bag->getDate('date', $default));
    }
}
