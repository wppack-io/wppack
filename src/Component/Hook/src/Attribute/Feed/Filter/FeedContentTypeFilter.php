<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Feed\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class FeedContentTypeFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('feed_content_type', $priority);
    }
}
