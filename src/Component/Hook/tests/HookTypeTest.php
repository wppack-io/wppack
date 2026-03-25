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

namespace WpPack\Component\Hook\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\HookType;

final class HookTypeTest extends TestCase
{
    #[Test]
    public function actionHasCorrectValue(): void
    {
        self::assertSame('action', HookType::Action->value);
    }

    #[Test]
    public function filterHasCorrectValue(): void
    {
        self::assertSame('filter', HookType::Filter->value);
    }

    #[Test]
    public function enumHasTwoCases(): void
    {
        self::assertCount(2, HookType::cases());
    }
}
