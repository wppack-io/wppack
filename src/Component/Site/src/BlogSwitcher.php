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

namespace WPPack\Component\Site;

final readonly class BlogSwitcher implements BlogSwitcherInterface
{
    public function __construct(
        private BlogContextInterface $context = new BlogContext(),
    ) {}

    public function switchToBlog(int $blogId): void
    {
        if (!\function_exists('switch_to_blog')) {
            return;
        }

        switch_to_blog($blogId);
    }

    public function restoreCurrentBlog(): void
    {
        if (!\function_exists('restore_current_blog')) {
            return;
        }

        restore_current_blog();
    }

    public function runInBlog(int $blogId, callable $callback): mixed
    {
        $this->switchToBlog($blogId);

        try {
            return $callback();
        } finally {
            $this->restoreCurrentBlog();
        }
    }

    public function runInBlogIfNeeded(int $blogId, callable $callback): mixed
    {
        if ($this->context->getCurrentBlogId() === $blogId) {
            return $callback();
        }

        return $this->runInBlog($blogId, $callback);
    }
}
