<?php

declare(strict_types=1);

namespace WpPack\Component\Site;

final class BlogContext implements BlogContextInterface
{
    public function getCurrentBlogId(): int
    {
        return get_current_blog_id();
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
