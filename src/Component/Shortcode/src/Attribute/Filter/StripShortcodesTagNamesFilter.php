<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class StripShortcodesTagNamesFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('strip_shortcodes_tag_names', $priority);
    }
}
