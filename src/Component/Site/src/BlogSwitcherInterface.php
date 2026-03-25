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
