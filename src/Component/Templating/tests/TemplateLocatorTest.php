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

namespace WPPack\Component\Templating\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Templating\TemplateLocator;

final class TemplateLocatorTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/Fixtures/templates';
    }

    #[Test]
    public function locatesTemplateInCustomPath(): void
    {
        $locator = new TemplateLocator([$this->fixturesPath]);

        $result = $locator->locate('simple');

        self::assertNotNull($result);
        self::assertStringEndsWith('simple.php', $result);
    }

    #[Test]
    public function returnsNullForMissingTemplate(): void
    {
        $locator = new TemplateLocator([$this->fixturesPath]);

        self::assertNull($locator->locate('nonexistent'));
    }

    #[Test]
    public function locatesTemplateWithVariant(): void
    {
        // Create a variant fixture
        $variantFile = $this->fixturesPath . '/simple-alt.php';
        file_put_contents($variantFile, '<h2>variant</h2>');

        try {
            $locator = new TemplateLocator([$this->fixturesPath]);

            $result = $locator->locate('simple', 'alt');

            self::assertNotNull($result);
            self::assertStringEndsWith('simple-alt.php', $result);
        } finally {
            unlink($variantFile);
        }
    }

    #[Test]
    public function fallsBackToBaseWhenVariantNotFound(): void
    {
        $locator = new TemplateLocator([$this->fixturesPath]);

        $result = $locator->locate('simple', 'nonexistent-variant');

        self::assertNotNull($result);
        self::assertStringEndsWith('simple.php', $result);
    }

    #[Test]
    public function locatesTemplateInSubdirectory(): void
    {
        $locator = new TemplateLocator([$this->fixturesPath]);

        $result = $locator->locate('partials/card');

        self::assertNotNull($result);
        self::assertStringEndsWith('partials/card.php', $result);
    }

    #[Test]
    public function locatesTemplateInLayoutsDirectory(): void
    {
        $locator = new TemplateLocator([$this->fixturesPath]);

        $result = $locator->locate('layouts/base');

        self::assertNotNull($result);
        self::assertStringEndsWith('layouts/base.php', $result);
    }

    #[Test]
    public function addPathAppendsSearchPath(): void
    {
        $locator = new TemplateLocator();

        $locator->addPath($this->fixturesPath);

        self::assertSame([$this->fixturesPath], $locator->getPaths());
        self::assertNotNull($locator->locate('simple'));
    }

    #[Test]
    public function searchesPathsInOrder(): void
    {
        $altPath = $this->fixturesPath . '/layouts';
        $locator = new TemplateLocator([$altPath, $this->fixturesPath]);

        $result = $locator->locate('base');

        // Should find it in the first path (layouts/)
        self::assertNotNull($result);
        self::assertStringContainsString('layouts', $result);
    }

    #[Test]
    public function getPathsReturnsRegisteredPaths(): void
    {
        $paths = ['/path/a', '/path/b'];
        $locator = new TemplateLocator($paths);

        self::assertSame($paths, $locator->getPaths());
    }

    #[Test]
    public function emptyLocatorReturnsNull(): void
    {
        $locator = new TemplateLocator();

        self::assertNull($locator->locate('anything'));
    }

    #[Test]
    public function locateRejectsParentTraversalInTemplateName(): void
    {
        $locator = new TemplateLocator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid template name');

        $locator->locate('../etc/passwd');
    }

    #[Test]
    public function locateRejectsNullByteInTemplateName(): void
    {
        $locator = new TemplateLocator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid template name');

        $locator->locate("page\0shell");
    }

    #[Test]
    public function locateReturnsThemeTemplateWhenWordpressLocatesIt(): void
    {
        // Drop a template into the active theme's directory so
        // locate_template() returns its absolute path, exercising the
        // early-return at line 51-53.
        $themeDir = get_stylesheet_directory();
        if (!is_dir($themeDir) || !is_writable($themeDir)) {
            self::markTestSkipped('Active theme directory is not writable.');
        }

        $name = 'wppack-locator-test-' . uniqid();
        $templateFile = $themeDir . '/' . $name . '.php';
        file_put_contents($templateFile, '<?php // fixture');

        try {
            $locator = new TemplateLocator();
            $result = $locator->locate($name);

            self::assertSame($templateFile, $result);
        } finally {
            @unlink($templateFile);
        }
    }
}
