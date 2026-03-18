<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Tests\ChannelResolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Logger\ChannelResolver\ChannelResolverInterface;
use WpPack\Component\Logger\ChannelResolver\WordPressChannelResolver;

final class WordPressChannelResolverTest extends TestCase
{
    private WordPressChannelResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new WordPressChannelResolver();
    }

    #[Test]
    public function implementsInterface(): void
    {
        self::assertInstanceOf(ChannelResolverInterface::class, $this->resolver);
    }

    #[Test]
    public function resolvesPluginDirectory(): void
    {
        $path = WP_PLUGIN_DIR . '/akismet/akismet.php';

        self::assertSame('plugin:akismet', $this->resolver->resolve($path));
    }

    #[Test]
    public function resolvesPluginSubdirectory(): void
    {
        $path = WP_PLUGIN_DIR . '/woocommerce/includes/class-wc-product.php';

        self::assertSame('plugin:woocommerce', $this->resolver->resolve($path));
    }

    #[Test]
    public function resolvesMuPluginAsPlugin(): void
    {
        $path = WPMU_PLUGIN_DIR . '/custom-mu/custom-mu.php';

        self::assertSame('plugin:custom-mu', $this->resolver->resolve($path));
    }

    #[Test]
    public function resolvesThemeDirectory(): void
    {
        $path = ABSPATH . 'wp-content/themes/twentytwentyfour/functions.php';

        self::assertSame('theme:twentytwentyfour', $this->resolver->resolve($path));
    }

    #[Test]
    public function resolvesWpIncludes(): void
    {
        $path = ABSPATH . 'wp-includes/plugin.php';

        self::assertSame('wordpress', $this->resolver->resolve($path));
    }

    #[Test]
    public function resolvesWpAdmin(): void
    {
        $path = ABSPATH . 'wp-admin/admin.php';

        self::assertSame('wordpress', $this->resolver->resolve($path));
    }

    #[Test]
    public function returnsFallbackForUnknownPath(): void
    {
        $path = '/usr/local/lib/php/some-library.php';

        self::assertSame('php', $this->resolver->resolve($path));
    }

    #[Test]
    public function returnsFallbackForEmptyPath(): void
    {
        self::assertSame('php', $this->resolver->resolve(''));
    }
}
