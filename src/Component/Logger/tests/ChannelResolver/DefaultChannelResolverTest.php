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

namespace WPPack\Component\Logger\Tests\ChannelResolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Logger\ChannelResolver\ChannelResolverInterface;
use WPPack\Component\Logger\ChannelResolver\DefaultChannelResolver;

final class DefaultChannelResolverTest extends TestCase
{
    #[Test]
    public function implementsInterface(): void
    {
        $resolver = new DefaultChannelResolver();

        self::assertInstanceOf(ChannelResolverInterface::class, $resolver);
    }

    #[Test]
    public function returnsDefaultChannel(): void
    {
        $resolver = new DefaultChannelResolver();

        self::assertSame('php', $resolver->resolve('/any/path/file.php'));
    }

    #[Test]
    public function returnsCustomDefaultChannel(): void
    {
        $resolver = new DefaultChannelResolver('custom');

        self::assertSame('custom', $resolver->resolve('/any/path/file.php'));
    }

    #[Test]
    public function alwaysReturnsSameChannelRegardlessOfPath(): void
    {
        $resolver = new DefaultChannelResolver();

        self::assertSame('php', $resolver->resolve('/wp-content/plugins/akismet/akismet.php'));
        self::assertSame('php', $resolver->resolve('/var/www/html/wp-includes/plugin.php'));
        self::assertSame('php', $resolver->resolve(''));
    }
}
