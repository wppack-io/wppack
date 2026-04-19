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

namespace WPPack\Component\Scheduler\Bridge\EventBridge;

use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;

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
