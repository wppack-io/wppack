<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Shortcode\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ShortcodeAttsFilter extends Filter
{
    public function __construct(
        public readonly string $shortcode,
        int $priority = 10,
    ) {
        parent::__construct("shortcode_atts_{$this->shortcode}", $priority);
    }
}
