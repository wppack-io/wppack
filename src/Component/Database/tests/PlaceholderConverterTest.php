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

namespace WPPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\PlaceholderConverter;

#[CoversClass(PlaceholderConverter::class)]
final class PlaceholderConverterTest extends TestCase
{
    #[Test]
    public function convertsStringPlaceholder(): void
    {
        [$sql, $params] = PlaceholderConverter::convert('SELECT * FROM t WHERE name = %s', ['alice']);

        self::assertSame('SELECT * FROM t WHERE name = ?', $sql);
        self::assertSame(['alice'], $params);
    }

    #[Test]
    public function convertsIntegerPlaceholder(): void
    {
        [$sql, $params] = PlaceholderConverter::convert('SELECT * FROM t WHERE id = %d', [42]);

        self::assertSame('SELECT * FROM t WHERE id = ?', $sql);
        self::assertSame([42], $params);
    }

    #[Test]
    public function convertsFloatPlaceholder(): void
    {
        [$sql, $params] = PlaceholderConverter::convert('SELECT * FROM t WHERE rate = %f', [3.14]);

        self::assertSame('SELECT * FROM t WHERE rate = ?', $sql);
        self::assertSame([3.14], $params);
    }

    #[Test]
    public function convertsMultiplePlaceholders(): void
    {
        [$sql, $params] = PlaceholderConverter::convert(
            'SELECT * FROM t WHERE name = %s AND id = %d AND rate = %f',
            ['alice', 42, 3.14],
        );

        self::assertSame('SELECT * FROM t WHERE name = ? AND id = ? AND rate = ?', $sql);
        self::assertSame(['alice', 42, 3.14], $params);
    }

    #[Test]
    public function preservesLiteralPercentPercent(): void
    {
        [$sql, $params] = PlaceholderConverter::convert('SELECT * FROM t WHERE name LIKE %s OR note = %%', ['a%']);

        self::assertSame('SELECT * FROM t WHERE name LIKE ? OR note = %%', $sql);
        self::assertSame(['a%'], $params);
    }

    #[Test]
    public function passesThroughQueryWithQuestionMarks(): void
    {
        [$sql, $params] = PlaceholderConverter::convert(
            'SELECT * FROM t WHERE id = ? AND name = ?',
            [42, 'alice'],
        );

        self::assertSame('SELECT * FROM t WHERE id = ? AND name = ?', $sql);
        self::assertSame([42, 'alice'], $params);
    }

    #[Test]
    public function passesThroughQueryWithoutAnyPlaceholder(): void
    {
        [$sql, $params] = PlaceholderConverter::convert('SELECT COUNT(*) FROM t', []);

        self::assertSame('SELECT COUNT(*) FROM t', $sql);
        self::assertSame([], $params);
    }

    #[Test]
    public function ignoresQuestionMarkInsideSingleQuotedStringLiteral(): void
    {
        // Query only uses %s — the ? inside the string literal must not flip the "already uses ?" detection
        [$sql, $params] = PlaceholderConverter::convert(
            "SELECT * FROM t WHERE a = 'what?' AND b = %s",
            ['x'],
        );

        self::assertSame("SELECT * FROM t WHERE a = 'what?' AND b = ?", $sql);
        self::assertSame(['x'], $params);
    }

    #[Test]
    public function ignoresQuestionMarkInsideDoubleQuotedStringLiteral(): void
    {
        [$sql, $params] = PlaceholderConverter::convert(
            'SELECT * FROM t WHERE a = "who?" AND b = %d',
            [1],
        );

        self::assertSame('SELECT * FROM t WHERE a = "who?" AND b = ?', $sql);
        self::assertSame([1], $params);
    }

    #[Test]
    public function preservesPercentSignWhenOnlyLiteralPercentPresent(): void
    {
        [$sql, $params] = PlaceholderConverter::convert('SELECT 100%% FROM t', []);

        self::assertSame('SELECT 100%% FROM t', $sql);
        self::assertSame([], $params);
    }

    #[Test]
    public function throwsWhenQueryMixesQuestionMarkAndPercentPlaceholders(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query contains both ? and %s/%d/%f placeholders');

        PlaceholderConverter::convert(
            'SELECT * FROM t WHERE id = ? AND name = %s',
            [1, 'test'],
        );
    }

    #[Test]
    public function doesNotConfuseQuestionMarkInsideLiteralAsMixingStyle(): void
    {
        // ? is inside a string literal — should not count as native placeholder
        // so %s conversion should still happen without raising mixed-style error
        [$sql, $params] = PlaceholderConverter::convert(
            "SELECT * FROM t WHERE a = 'a?b' AND b = %s",
            ['x'],
        );

        self::assertSame("SELECT * FROM t WHERE a = 'a?b' AND b = ?", $sql);
        self::assertSame(['x'], $params);
    }
}
