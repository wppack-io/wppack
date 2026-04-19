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
use WPPack\Component\Messenger\Stamp\MultisiteStamp;
use WPPack\Component\Messenger\Stamp\StampInterface;

#[CoversClass(MultisiteStamp::class)]
final class MultisiteStampTest extends TestCase
{
    #[Test]
    public function constructionAndPropertyAccess(): void
    {
        $stamp = new MultisiteStamp(42);

        self::assertSame(42, $stamp->blogId);
        self::assertInstanceOf(StampInterface::class, $stamp);
    }

    #[Test]
    public function defaultBlogId(): void
    {
        $stamp = new MultisiteStamp(1);

        self::assertSame(1, $stamp->blogId);
    }
}
