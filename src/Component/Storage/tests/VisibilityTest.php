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

namespace WPPack\Component\Storage\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Storage\Visibility;

#[CoversClass(Visibility::class)]
final class VisibilityTest extends TestCase
{
    #[Test]
    public function casesMatchFlysystemConventions(): void
    {
        self::assertSame('public', Visibility::PUBLIC->value);
        self::assertSame('private', Visibility::PRIVATE->value);
        self::assertCount(2, Visibility::cases());
    }

    #[Test]
    public function fromAcceptsCanonicalValues(): void
    {
        self::assertSame(Visibility::PUBLIC, Visibility::from('public'));
        self::assertSame(Visibility::PRIVATE, Visibility::from('private'));
    }

    #[Test]
    public function invalidValueThrowsValueError(): void
    {
        $this->expectException(\ValueError::class);

        Visibility::from('partial');
    }
}
