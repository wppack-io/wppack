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

namespace WPPack\Component\Debug\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\CssTheme;

#[CoversClass(CssTheme::class)]
final class CssThemeTest extends TestCase
{
    #[Test]
    public function cssVariablesReturnsNonEmptyDeclarations(): void
    {
        $css = CssTheme::cssVariables();

        self::assertNotSame('', $css);
        self::assertStringContainsString('--wpd-gray-900', $css);
        self::assertStringContainsString('--wpd-primary', $css);
        self::assertStringContainsString('--wpd-font-mono', $css);
        self::assertStringContainsString('--wpd-radius', $css);
    }

    #[Test]
    public function cssVariablesHasNoSelectorWrapper(): void
    {
        $css = CssTheme::cssVariables();

        self::assertStringNotContainsString('{', $css);
        self::assertStringNotContainsString('}', $css);
    }

    #[Test]
    public function cssVariablesEveryLineIsCssDecl(): void
    {
        $css = CssTheme::cssVariables();

        foreach (explode("\n", trim($css)) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '/*')) {
                continue;
            }
            self::assertStringStartsWith('--', $trimmed, "declaration line: {$trimmed}");
            self::assertStringEndsWith(';', $trimmed, "declaration terminator: {$trimmed}");
        }
    }
}
