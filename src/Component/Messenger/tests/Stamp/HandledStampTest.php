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

namespace WPPack\Component\Messenger\Tests\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Messenger\Stamp\HandledStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(HandledStamp::class)]
final class HandledStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new HandledStamp('result-value', 'MyHandler');

        self::assertSame('result-value', $stamp->result);
        self::assertSame('MyHandler', $stamp->handlerName);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function nullResult(): void
    {
        $stamp = new HandledStamp(null, 'VoidHandler');

        self::assertNull($stamp->result);
        self::assertSame('VoidHandler', $stamp->handlerName);
    }

    #[Test]
    public function objectResult(): void
    {
        $result = new \stdClass();
        $stamp = new HandledStamp($result, 'ObjectHandler');

        self::assertSame($result, $stamp->result);
    }

    #[Test]
    public function integerResult(): void
    {
        $stamp = new HandledStamp(42, 'IntHandler');

        self::assertSame(42, $stamp->result);
    }

    #[Test]
    public function arrayResult(): void
    {
        $stamp = new HandledStamp(['key' => 'value'], 'ArrayHandler');

        self::assertSame(['key' => 'value'], $stamp->result);
    }
}
