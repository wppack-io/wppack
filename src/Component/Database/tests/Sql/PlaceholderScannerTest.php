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

namespace WpPack\Component\Database\Tests\Sql;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Sql\PlaceholderScanner;

final class PlaceholderScannerTest extends TestCase
{
    #[Test]
    public function replacesPlaceholdersWithGivenIndex(): void
    {
        $out = PlaceholderScanner::replace(
            'a = ? AND b = ?',
            static fn(int $i): string => '$' . ($i + 1),
        );

        self::assertSame('a = $1 AND b = $2', $out);
    }

    #[Test]
    public function leavesPlaceholderInsideSingleQuoteAlone(): void
    {
        $out = PlaceholderScanner::replace(
            "name = 'What?' AND id = ?",
            static fn(int $i): string => 'X' . $i,
        );

        self::assertSame("name = 'What?' AND id = X0", $out);
    }

    #[Test]
    public function handlesDoubledQuoteEscape(): void
    {
        $out = PlaceholderScanner::replace(
            "name = 'O''Brien' AND id = ?",
            static fn(int $i): string => 'X',
        );

        self::assertSame("name = 'O''Brien' AND id = X", $out);
    }

    #[Test]
    public function handlesBackslashEscapeInsideLiteral(): void
    {
        $out = PlaceholderScanner::replace(
            "name = 'a\\'b' AND id = ?",
            static fn(int $i): string => 'X',
        );

        self::assertSame("name = 'a\\'b' AND id = X", $out);
    }

    #[Test]
    public function noPlaceholdersReturnsInputUnchanged(): void
    {
        $sql = "SELECT * FROM x WHERE y = 'literal'";

        self::assertSame($sql, PlaceholderScanner::replace($sql, static fn() => '!'));
    }

    #[Test]
    public function indexIsZeroBasedAndSequential(): void
    {
        $seen = [];
        PlaceholderScanner::replace(
            '? ? ?',
            static function (int $i) use (&$seen): string {
                $seen[] = $i;

                return '';
            },
        );

        self::assertSame([0, 1, 2], $seen);
    }
}
