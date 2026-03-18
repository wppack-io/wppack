<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Sanitizer\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SanitizeCommentMetaFilter extends Filter
{
    public function __construct(string $metaKey, int $priority = 10)
    {
        parent::__construct("sanitize_comment_meta_{$metaKey}", $priority);
    }
}
