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

namespace WpPack\Component\Storage\Tests\StreamWrapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\StreamWrapper\StatCache;

#[CoversClass(StatCache::class)]
final class StatCacheTest extends TestCase
{
    public function testGetReturnsNullForMissingKey(): void
    {
        $cache = new StatCache();

        self::assertNull($cache->get('missing'));
    }

    public function testSetAndGet(): void
    {
        $cache = new StatCache();
        $stat = ['size' => 42, 7 => 42];

        $cache->set('s3://bucket/file.txt', $stat);

        self::assertSame($stat, $cache->get('s3://bucket/file.txt'));
    }

    public function testSetOverwritesExistingEntry(): void
    {
        $cache = new StatCache();

        $cache->set('s3://bucket/file.txt', ['size' => 10, 7 => 10]);
        $cache->set('s3://bucket/file.txt', ['size' => 20, 7 => 20]);

        self::assertSame(['size' => 20, 7 => 20], $cache->get('s3://bucket/file.txt'));
    }

    public function testRemove(): void
    {
        $cache = new StatCache();
        $cache->set('s3://bucket/file.txt', ['size' => 42, 7 => 42]);

        $cache->remove('s3://bucket/file.txt');

        self::assertNull($cache->get('s3://bucket/file.txt'));
    }

    public function testRemoveNonExistentKeyDoesNothing(): void
    {
        $cache = new StatCache();

        $cache->remove('nonexistent');

        self::assertNull($cache->get('nonexistent'));
    }

    public function testClear(): void
    {
        $cache = new StatCache();
        $cache->set('s3://bucket/a.txt', ['size' => 1, 7 => 1]);
        $cache->set('s3://bucket/b.txt', ['size' => 2, 7 => 2]);

        $cache->clear();

        self::assertNull($cache->get('s3://bucket/a.txt'));
        self::assertNull($cache->get('s3://bucket/b.txt'));
    }

    public function testEvictsOldestEntryWhenMaxSizeReached(): void
    {
        $cache = new StatCache(maxSize: 2);

        $cache->set('s3://bucket/a.txt', ['size' => 1, 7 => 1]);
        $cache->set('s3://bucket/b.txt', ['size' => 2, 7 => 2]);
        $cache->set('s3://bucket/c.txt', ['size' => 3, 7 => 3]);

        self::assertNull($cache->get('s3://bucket/a.txt'));
        self::assertSame(['size' => 2, 7 => 2], $cache->get('s3://bucket/b.txt'));
        self::assertSame(['size' => 3, 7 => 3], $cache->get('s3://bucket/c.txt'));
    }

    public function testOverwriteDoesNotCountTowardMaxSize(): void
    {
        $cache = new StatCache(maxSize: 2);

        $cache->set('s3://bucket/a.txt', ['size' => 1, 7 => 1]);
        $cache->set('s3://bucket/b.txt', ['size' => 2, 7 => 2]);

        // Overwrite existing — should NOT evict 'a'
        $cache->set('s3://bucket/b.txt', ['size' => 22, 7 => 22]);

        self::assertSame(['size' => 1, 7 => 1], $cache->get('s3://bucket/a.txt'));
        self::assertSame(['size' => 22, 7 => 22], $cache->get('s3://bucket/b.txt'));
    }
}
