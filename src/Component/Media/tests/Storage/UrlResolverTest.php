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

namespace WPPack\Component\Media\Tests\Storage;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Media\Storage\UrlResolver;
use WPPack\Component\Storage\Test\InMemoryStorageAdapter;

final class UrlResolverTest extends TestCase
{
    #[Test]
    public function resolveWithCdnUrlReturnsCdnUrlPlusKey(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $resolver = new UrlResolver($adapter, 'https://cdn.example.com');

        self::assertSame('https://cdn.example.com/uploads/2024/01/image.jpg', $resolver->resolve('uploads/2024/01/image.jpg'));
    }

    #[Test]
    public function resolveWithCdnUrlTrimsTrailingSlash(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $resolver = new UrlResolver($adapter, 'https://cdn.example.com/');

        self::assertSame('https://cdn.example.com/uploads/image.jpg', $resolver->resolve('uploads/image.jpg'));
    }

    #[Test]
    public function resolveWithCdnUrlTrimsLeadingSlashFromKey(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $resolver = new UrlResolver($adapter, 'https://cdn.example.com');

        self::assertSame('https://cdn.example.com/uploads/image.jpg', $resolver->resolve('/uploads/image.jpg'));
    }

    #[Test]
    public function resolveWithoutCdnUrlDelegatesToAdapterPublicUrl(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $resolver = new UrlResolver($adapter);

        self::assertSame('memory://uploads/image.jpg', $resolver->resolve('uploads/image.jpg'));
    }

    #[Test]
    public function resolveWithNullCdnUrlDelegatesToAdapterPublicUrl(): void
    {
        $adapter = new InMemoryStorageAdapter();
        $resolver = new UrlResolver($adapter, null);

        self::assertSame('memory://uploads/2024/01/photo.png', $resolver->resolve('uploads/2024/01/photo.png'));
    }
}
