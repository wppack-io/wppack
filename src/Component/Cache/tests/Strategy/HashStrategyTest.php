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

namespace WPPack\Component\Cache\Tests\Strategy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Cache\Strategy\AllOptionsHashStrategy;
use WPPack\Component\Cache\Strategy\NotOptionsHashStrategy;
use WPPack\Component\Cache\Strategy\SiteNotOptionsHashStrategy;
use WPPack\Component\Cache\Strategy\SiteOptionsHashStrategy;

#[CoversClass(AllOptionsHashStrategy::class)]
#[CoversClass(NotOptionsHashStrategy::class)]
#[CoversClass(SiteOptionsHashStrategy::class)]
#[CoversClass(SiteNotOptionsHashStrategy::class)]
final class HashStrategyTest extends TestCase
{
    // ── AllOptionsHashStrategy ─────────────────────────────────────────

    #[Test]
    public function allOptionsOnlySupportsOptionsAllopOptions(): void
    {
        $strategy = new AllOptionsHashStrategy();

        self::assertTrue($strategy->supports('alloptions', 'options'));
        self::assertFalse($strategy->supports('alloptions', 'other'));
        self::assertFalse($strategy->supports('something', 'options'));
    }

    #[Test]
    public function allOptionsSerializesScalarsArraysAndObjects(): void
    {
        $strategy = new AllOptionsHashStrategy();

        $value = [
            'blogname' => 'Example',
            'blog_public' => '1',
            'config' => ['debug' => true, 'locale' => 'en_US'],
        ];

        $fields = $strategy->serialize($value);

        foreach ($fields as $name => $serialized) {
            self::assertIsString($serialized);
            self::assertSame($value[$name], unserialize($serialized));
        }
    }

    #[Test]
    public function allOptionsSerializeDeserializeRoundTrip(): void
    {
        $strategy = new AllOptionsHashStrategy();

        $original = [
            'bool' => false,
            'int' => 42,
            'array' => ['a', 'b', 'c'],
            'object' => (object) ['x' => 1],
        ];

        $deserialized = $strategy->deserialize($strategy->serialize($original));

        self::assertSame(false, $deserialized['bool']);
        self::assertSame(42, $deserialized['int']);
        self::assertSame(['a', 'b', 'c'], $deserialized['array']);
        self::assertEquals((object) ['x' => 1], $deserialized['object']);
    }

    #[Test]
    public function allOptionsCoercesNumericKeysToStrings(): void
    {
        $strategy = new AllOptionsHashStrategy();

        $fields = $strategy->serialize([0 => 'a', 1 => 'b']);

        self::assertArrayHasKey('0', $fields);
        self::assertArrayHasKey('1', $fields);
    }

    // ── NotOptionsHashStrategy ─────────────────────────────────────────

    #[Test]
    public function notOptionsOnlySupportsOptionsNotoptionsKey(): void
    {
        $strategy = new NotOptionsHashStrategy();

        self::assertTrue($strategy->supports('notoptions', 'options'));
        self::assertFalse($strategy->supports('notoptions', 'other'));
        self::assertFalse($strategy->supports('alloptions', 'options'));
    }

    #[Test]
    public function notOptionsStoresTruthyFlag(): void
    {
        $strategy = new NotOptionsHashStrategy();

        $fields = $strategy->serialize(['missing_option' => true, 'another' => true]);

        self::assertSame(['missing_option' => '1', 'another' => '1'], $fields);
    }

    #[Test]
    public function notOptionsDeserializeReturnsAllTrue(): void
    {
        $strategy = new NotOptionsHashStrategy();

        $value = $strategy->deserialize(['a' => '1', 'b' => '1']);

        self::assertSame(['a' => true, 'b' => true], $value);
    }

    // ── SiteOptionsHashStrategy ────────────────────────────────────────

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function siteOptionsSupports(): iterable
    {
        yield 'all-blog-1' => ['1:all', 'site-options', true];
        yield 'all-blog-42' => ['42:all', 'site-options', true];
        yield 'wrong-group' => ['1:all', 'options', false];
        yield 'wrong-key' => ['1:alloptions', 'site-options', false];
        yield 'empty-key' => ['', 'site-options', false];
    }

    #[Test]
    #[DataProvider('siteOptionsSupports')]
    public function siteOptionsSupportPredicate(string $key, string $group, bool $expected): void
    {
        self::assertSame($expected, (new SiteOptionsHashStrategy())->supports($key, $group));
    }

    #[Test]
    public function siteOptionsRoundTrip(): void
    {
        $strategy = new SiteOptionsHashStrategy();

        $original = ['site_url' => 'https://example.com', 'admin_email' => 'a@e.com'];
        $deserialized = $strategy->deserialize($strategy->serialize($original));

        self::assertSame($original, $deserialized);
    }

    // ── SiteNotOptionsHashStrategy ─────────────────────────────────────

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function siteNotOptionsSupports(): iterable
    {
        yield 'notoptions-blog-1' => ['1:notoptions', 'site-options', true];
        yield 'notoptions-blog-99' => ['99:notoptions', 'site-options', true];
        yield 'wrong-group' => ['1:notoptions', 'options', false];
        yield 'wrong-suffix' => ['1:all', 'site-options', false];
    }

    #[Test]
    #[DataProvider('siteNotOptionsSupports')]
    public function siteNotOptionsSupportPredicate(string $key, string $group, bool $expected): void
    {
        self::assertSame($expected, (new SiteNotOptionsHashStrategy())->supports($key, $group));
    }

    #[Test]
    public function siteNotOptionsStoresTruthyFlag(): void
    {
        $strategy = new SiteNotOptionsHashStrategy();

        $fields = $strategy->serialize(['missing' => true, 'other' => true]);

        self::assertSame(['missing' => '1', 'other' => '1'], $fields);
    }

    #[Test]
    public function siteNotOptionsDeserializeReturnsAllTrue(): void
    {
        $strategy = new SiteNotOptionsHashStrategy();

        self::assertSame(['a' => true, 'b' => true], $strategy->deserialize(['a' => '1', 'b' => '1']));
    }
}
