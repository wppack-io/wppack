<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;

final class MultisiteScheduleGroupResolver implements ScheduleGroupResolverInterface
{
    public function __construct(
        private readonly string $prefix = 'wppack',
        private readonly BlogContextInterface $blogContext = new BlogContext(),
    ) {}

    public function resolve(?int $blogId = null): string
    {
        $blogId ??= $this->blogContext->getCurrentBlogId();

        if ($blogId <= 0 || $blogId === $this->blogContext->getMainSiteId()) {
            return $this->prefix;
        }

        return $this->prefix . '_' . $blogId;
    }
}
