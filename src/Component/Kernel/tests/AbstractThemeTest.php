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

namespace WpPack\Component\Kernel\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Kernel\AbstractTheme;
use WpPack\Component\Kernel\ThemeInterface;

final class AbstractThemeTest extends TestCase
{
    #[Test]
    public function implementsThemeInterface(): void
    {
        $theme = $this->createTheme(__FILE__);

        self::assertInstanceOf(ThemeInterface::class, $theme);
    }

    #[Test]
    public function getFileReturnsThemeFile(): void
    {
        $file = '/path/to/themes/my-theme/functions.php';
        $theme = $this->createTheme($file);

        self::assertSame($file, $theme->getFile());
    }

    #[Test]
    public function getPathReturnsDirectoryWithTrailingSlash(): void
    {
        $theme = $this->createTheme('/path/to/themes/my-theme/functions.php');

        $path = $theme->getPath();

        self::assertSame('/path/to/themes/my-theme/', $path);
    }

    #[Test]
    public function getUrlReturnsThemeDirectoryUrl(): void
    {
        $themesDir = get_theme_root();
        $theme = $this->createTheme($themesDir . '/my-theme/functions.php');

        $url = $theme->getUrl();

        self::assertStringContainsString('my-theme/', $url);
        self::assertStringEndsWith('/', $url);
    }

    #[Test]
    public function getCompilerPassesReturnsEmptyArray(): void
    {
        $theme = $this->createTheme(__FILE__);

        self::assertSame([], $theme->getCompilerPasses());
    }

    #[Test]
    public function bootDoesNotThrow(): void
    {
        $theme = $this->createTheme(__FILE__);
        $container = new \WpPack\Component\DependencyInjection\Container(new \Symfony\Component\DependencyInjection\Container());

        $theme->boot($container);

        $this->addToAssertionCount(1);
    }

    private function createTheme(string $themeFile): AbstractTheme
    {
        return new class ($themeFile) extends AbstractTheme {
            public function register(\WpPack\Component\DependencyInjection\ContainerBuilder $builder): void {}
        };
    }
}
