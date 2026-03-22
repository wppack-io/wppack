<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

interface BlogSwitcherInterface
{
    public function switchToBlog(int $blogId): void;

    public function restoreCurrentBlog(): void;

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
