<?php

declare(strict_types=1);

namespace WpPack\Component\Sanitizer\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SanitizePostMetaFilter extends Filter
{
    public function __construct(string $metaKey, int $priority = 10)
    {
        parent::__construct("sanitize_post_meta_{$metaKey}", $priority);
    }
}
