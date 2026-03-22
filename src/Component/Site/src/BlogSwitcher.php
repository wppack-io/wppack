<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

final readonly class BlogSwitcher implements BlogSwitcherInterface
{
    public function __construct(
        private BlogContextInterface $context = new BlogContext(),
    ) {}

    public function runInBlog(int $blogId, callable $callback): mixed
    {
        if (!\function_exists('switch_to_blog')) {
            return $callback();
        }

        switch_to_blog($blogId);

        try {
            return $callback();
        } finally {
            restore_current_blog();
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
