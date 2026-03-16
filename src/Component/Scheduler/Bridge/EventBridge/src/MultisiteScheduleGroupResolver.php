<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

final class MultisiteScheduleGroupResolver
{
    private const DEFAULT_GROUP = 'wppack';

    public function resolve(?int $blogId = null): string
    {
        $blogId ??= $this->getCurrentBlogId();

        if ($blogId <= 1) {
            return self::DEFAULT_GROUP;
        }

        return self::DEFAULT_GROUP . '_' . $blogId;
    }

    private function getCurrentBlogId(): int
    {
        if (\function_exists('get_current_blog_id')) {
            return get_current_blog_id();
        }

        return 1;
    }
}
