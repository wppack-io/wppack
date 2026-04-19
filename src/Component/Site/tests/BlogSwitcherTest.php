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

namespace WPPack\Component\Site\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\Site\BlogSwitcher;

#[CoversClass(BlogSwitcher::class)]
final class BlogSwitcherTest extends TestCase
{
    #[Test]
    public function runInBlogExecutesCallbackAndReturnsValue(): void
    {
        $switcher = new BlogSwitcher();

        $result = $switcher->runInBlog(1, static fn(): string => 'hello');

        self::assertSame('hello', $result);
    }

    #[Test]
    public function runInBlogRestoresOnException(): void
    {
        $switcher = new BlogSwitcher();
        $originalBlogId = get_current_blog_id();

        try {
            $switcher->runInBlog(1, static function (): never {
                throw new \RuntimeException('Test exception');
            });
        } catch (\RuntimeException) {
            // Expected
        }

        self::assertSame($originalBlogId, get_current_blog_id());
    }

    #[Test]
    public function runInBlogIfNeededSkipsSwitchForCurrentBlog(): void
    {
        $context = $this->createMock(BlogContextInterface::class);
        $context->method('getCurrentBlogId')->willReturn(1);

        $switcher = new BlogSwitcher($context);

        $result = $switcher->runInBlogIfNeeded(1, static fn(): string => 'skipped');

        self::assertSame('skipped', $result);
    }

    #[Test]
    public function runInBlogIfNeededSwitchesForDifferentBlog(): void
    {
        $context = $this->createMock(BlogContextInterface::class);
        $context->method('getCurrentBlogId')->willReturn(1);

        $switcher = new BlogSwitcher($context);

        $result = $switcher->runInBlogIfNeeded(2, static fn(): string => 'switched');

        self::assertSame('switched', $result);
    }

    #[Test]
    public function switchToBlogSwitchesContext(): void
    {
        if (!is_multisite()) {
            self::markTestSkipped('Multisite required.');
        }

        $switcher = new BlogSwitcher();
        $originalBlogId = get_current_blog_id();

        $switcher->switchToBlog(2);
        self::assertSame(2, get_current_blog_id());

        $switcher->restoreCurrentBlog();
        self::assertSame($originalBlogId, get_current_blog_id());
    }

    #[Test]
    public function restoreCurrentBlogWithoutPriorSwitchDoesNotThrow(): void
    {
        $switcher = new BlogSwitcher();
        $originalBlogId = get_current_blog_id();

        $switcher->restoreCurrentBlog();

        self::assertSame($originalBlogId, get_current_blog_id());
    }

    #[Test]
    public function switchToBlogNestedCallsRestoreCorrectly(): void
    {
        if (!is_multisite()) {
            self::markTestSkipped('Multisite required.');
        }

        $switcher = new BlogSwitcher();
        $originalBlogId = get_current_blog_id();

        $switcher->switchToBlog(2);
        self::assertSame(2, get_current_blog_id());

        $switcher->switchToBlog(3);
        self::assertSame(3, get_current_blog_id());

        $switcher->restoreCurrentBlog();
        self::assertSame(2, get_current_blog_id());

        $switcher->restoreCurrentBlog();
        self::assertSame($originalBlogId, get_current_blog_id());
    }
}
