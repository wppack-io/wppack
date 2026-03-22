<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

interface BlogSwitcherInterface
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runInBlog(int $blogId, callable $callback): mixed;

    /**
     * Skip switching if already on the target blog.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function runInBlogIfNeeded(int $blogId, callable $callback): mixed;
}
