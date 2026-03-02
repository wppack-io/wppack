<?php

declare(strict_types=1);

namespace WpPack\Component\Option\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SiteOptionFilter extends Filter
{
    public function __construct(
        public readonly string $optionName,
        int $priority = 10,
    ) {
        parent::__construct("site_option_{$this->optionName}", $priority);
    }
}
