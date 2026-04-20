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

namespace WPPack\Component\Debug\Tests\Toolbar\Panel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\Toolbar\Panel\ToolbarIcons;

#[CoversClass(ToolbarIcons::class)]
final class ToolbarIconsTest extends TestCase
{
    #[Test]
    public function svgWrapsRegisteredPathInSvgTag(): void
    {
        $svg = ToolbarIcons::svg('performance');

        self::assertStringStartsWith('<svg', $svg);
        self::assertStringContainsString('viewBox="0 0 24 24"', $svg);
        self::assertStringContainsString('width="16"', $svg);
        self::assertStringContainsString('height="16"', $svg);
        self::assertStringContainsString('stroke="currentColor"', $svg);
        self::assertStringContainsString('aria-hidden="true"', $svg);
        self::assertStringEndsWith('</svg>', $svg);
    }

    #[Test]
    public function svgFallsBackToDefaultClipboardPathForUnknownName(): void
    {
        $default = ToolbarIcons::svg('never-registered-icon-' . uniqid());
        $clipboard = ToolbarIcons::svg('definitely-not-a-key');

        // Both unknown names resolve to the same default clipboard path
        self::assertSame($default, $clipboard);
        self::assertStringContainsString('<rect width="8" height="4"', $default);
    }

    #[Test]
    public function svgSizeIsCustomisable(): void
    {
        $svg = ToolbarIcons::svg('close', 32);

        self::assertStringContainsString('width="32"', $svg);
        self::assertStringContainsString('height="32"', $svg);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function knownIconProvider(): iterable
    {
        yield 'performance' => ['performance'];
        yield 'close' => ['close'];
    }

    #[Test]
    #[DataProvider('knownIconProvider')]
    public function knownIconsResolveToNonDefaultPath(string $name): void
    {
        $svg = ToolbarIcons::svg($name);
        $fallback = ToolbarIcons::svg('totally-unknown-name');

        self::assertNotSame($svg, $fallback, "{$name} should map to a custom icon, not the clipboard fallback");
    }
}
