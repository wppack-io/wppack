<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Option\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreUpdateOptionFilter extends Filter
{
    public function __construct(
        public readonly string $optionName,
        int $priority = 10,
    ) {
        parent::__construct("pre_update_option_{$this->optionName}", $priority);
    }
}
