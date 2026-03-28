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

namespace WpPack\Component\Scim\Repository;

use WpPack\Component\Site\BlogSwitcherInterface;
use WpPack\Component\Site\SiteRepositoryInterface;

trait MultisiteAwareTrait
{
    private function forEachSite(callable $callback): void
    {
        if ($this->blogSwitcher !== null && $this->siteRepository !== null) {
            foreach ($this->siteRepository->findAll() as $site) {
                $this->blogSwitcher->runInBlog((int) $site->blog_id, $callback);
            }
        } else {
            $callback();
        }
    }
}
