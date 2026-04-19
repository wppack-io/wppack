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

namespace WPPack\Component\Ajax\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Ajax\Access;

final class AccessTest extends TestCase
{
    #[Test]
    public function publicCaseExists(): void
    {
        self::assertSame('Public', Access::Public->name);
    }

    #[Test]
    public function authenticatedCaseExists(): void
    {
        self::assertSame('Authenticated', Access::Authenticated->name);
    }

    #[Test]
    public function guestCaseExists(): void
    {
        self::assertSame('Guest', Access::Guest->name);
    }

    #[Test]
    public function hasExactlyThreeCases(): void
    {
        self::assertCount(3, Access::cases());
    }
}
