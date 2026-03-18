<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Sanitizer\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SanitizeKeyFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('sanitize_key', $priority);
    }
}
