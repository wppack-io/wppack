<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Query\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UpdatePostMetaCacheFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('update_post_meta_cache', $priority);
    }
}
