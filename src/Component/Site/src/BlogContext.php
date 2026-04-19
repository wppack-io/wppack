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

final readonly class BlogContext implements BlogContextInterface
{
    public function getCurrentBlogId(): int
    {
        return get_current_blog_id();
    }

    public function getMainSiteId(): int
    {
        return get_main_site_id();
    }

    public function isMultisite(): bool
    {
        return is_multisite();
    }

    public function isSwitched(): bool
    {
        if (!\function_exists('ms_is_switched')) {
            return false;
        }

        return ms_is_switched();
    }
}
