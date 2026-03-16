<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

final class MultisiteScheduleGroupResolver implements ScheduleGroupResolverInterface
{
    public function __construct(
        private readonly string $prefix = 'wppack',
    ) {}

    public function resolve(?int $blogId = null): string
    {
        $blogId ??= $this->getCurrentBlogId();

        if ($blogId <= 1) {
            return $this->prefix;
        }

        return $this->prefix . '_' . $blogId;
    }

    private function getCurrentBlogId(): int
    {
        if (\function_exists('get_current_blog_id')) {
            return get_current_blog_id();
        }

        return 1;
    }
}
