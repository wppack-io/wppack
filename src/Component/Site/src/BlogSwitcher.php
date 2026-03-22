<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

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
