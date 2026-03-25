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

namespace WpPack\Component\Site\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Site\BlogContext;

#[CoversClass(BlogContext::class)]
final class BlogContextTest extends TestCase
{
    private BlogContext $context;

    protected function setUp(): void
    {
        $this->context = new BlogContext();
    }

    #[Test]
    public function getCurrentBlogIdReturnsOne(): void
    {
        self::assertSame(1, $this->context->getCurrentBlogId());
    }

    #[Test]
    public function isMultisiteReturnsFalseInSingleSite(): void
    {
        self::assertFalse($this->context->isMultisite());
    }

    #[Test]
    public function isSwitchedReturnsFalseByDefault(): void
    {
        self::assertFalse($this->context->isSwitched());
    }
}
