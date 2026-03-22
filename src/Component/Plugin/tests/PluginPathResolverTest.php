<?php

declare(strict_types=1);

namespace WpPack\Component\Plugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Plugin\PluginPathResolver;

#[CoversClass(PluginPathResolver::class)]
final class PluginPathResolverTest extends TestCase
{
    private PluginPathResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PluginPathResolver(WP_PLUGIN_DIR . '/test-plugin/test-plugin.php');
    }

    #[Test]
    public function getUrlReturnsPluginDirectoryUrl(): void
    {
        $url = $this->resolver->getUrl();

        self::assertStringContainsString('test-plugin/', $url);
        self::assertStringEndsWith('/', $url);
    }

    #[Test]
    public function getPathReturnsPluginDirectoryPath(): void
    {
        $path = $this->resolver->getPath();

        self::assertStringEndsWith('test-plugin/', $path);
        self::assertStringEndsWith(\DIRECTORY_SEPARATOR, $path);
    }

    #[Test]
    public function getBasenameReturnsPluginBasename(): void
    {
        $basename = $this->resolver->getBasename();

        self::assertSame('test-plugin/test-plugin.php', $basename);
    }
}
