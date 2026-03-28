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

namespace WpPack\Component\Scim\Controller;

trait MultisiteScopeTrait
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function inBlogContext(callable $callback): mixed
    {
        if ($this->blogId !== null && $this->blogSwitcher !== null) {
            return $this->blogSwitcher->runInBlogIfNeeded($this->blogId, $callback);
        }

        return $callback();
    }
}
