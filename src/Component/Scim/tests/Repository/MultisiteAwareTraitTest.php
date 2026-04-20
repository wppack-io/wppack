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

namespace WPPack\Component\Scim\Tests\Repository;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Repository\MultisiteAwareTrait;
use WPPack\Component\Site\BlogSwitcherInterface;
use WPPack\Component\Site\SiteRepositoryInterface;

#[CoversClass(MultisiteAwareTrait::class)]
final class MultisiteAwareTraitTest extends TestCase
{
    private function harness(
        ?BlogSwitcherInterface $blogSwitcher = null,
        ?SiteRepositoryInterface $siteRepository = null,
    ): object {
        return new class ($blogSwitcher, $siteRepository) {
            use MultisiteAwareTrait;

            public function __construct(
                private readonly ?BlogSwitcherInterface $blogSwitcher = null,
                private readonly ?SiteRepositoryInterface $siteRepository = null,
            ) {}

            public function run(callable $callback): void
            {
                $this->forEachSite($callback);
            }
        };
    }

    #[Test]
    public function singleSiteRunsCallbackOnceWhenDepsMissing(): void
    {
        $calls = 0;
        $this->harness()->run(function () use (&$calls): void {
            $calls++;
        });

        self::assertSame(1, $calls);
    }

    #[Test]
    public function multisiteIteratesEachSite(): void
    {
        $siteA = new \stdClass();
        $siteA->blog_id = 1;
        $siteB = new \stdClass();
        $siteB->blog_id = 2;

        $repository = $this->createMock(SiteRepositoryInterface::class);
        $repository->method('findAll')->willReturn([$siteA, $siteB]);

        $switcher = $this->createMock(BlogSwitcherInterface::class);
        $switcher->expects(self::exactly(2))
            ->method('runInBlog')
            ->willReturnCallback(function (int $blogId, callable $cb) use (&$captured): mixed {
                $captured[] = $blogId;
                return $cb();
            });

        $captured = [];
        $calls = 0;
        $this->harness($switcher, $repository)->run(function () use (&$calls): void {
            $calls++;
        });

        self::assertSame(2, $calls, 'callback invoked once per site');
        self::assertSame([1, 2], $captured);
    }

    #[Test]
    public function multisiteWithZeroSitesDoesNotInvokeCallback(): void
    {
        $repository = $this->createMock(SiteRepositoryInterface::class);
        $repository->method('findAll')->willReturn([]);
        $switcher = $this->createMock(BlogSwitcherInterface::class);

        $calls = 0;
        $this->harness($switcher, $repository)->run(function () use (&$calls): void {
            $calls++;
        });

        self::assertSame(0, $calls);
    }

    #[Test]
    public function fallbackTakesOverWhenOnlyBlogSwitcherProvided(): void
    {
        // Both need to be set for multisite mode — either missing → single-site.
        $switcher = $this->createMock(BlogSwitcherInterface::class);
        $switcher->expects(self::never())->method('runInBlog');

        $calls = 0;
        $this->harness(blogSwitcher: $switcher)->run(function () use (&$calls): void {
            $calls++;
        });

        self::assertSame(1, $calls);
    }

    #[Test]
    public function fallbackTakesOverWhenOnlySiteRepositoryProvided(): void
    {
        $repository = $this->createMock(SiteRepositoryInterface::class);
        $repository->expects(self::never())->method('findAll');

        $calls = 0;
        $this->harness(siteRepository: $repository)->run(function () use (&$calls): void {
            $calls++;
        });

        self::assertSame(1, $calls);
    }
}
