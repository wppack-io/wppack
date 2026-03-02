<?php

declare(strict_types=1);

namespace WpPack\Component\Option\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class OptionFilter extends Filter
{
    public function __construct(
        public readonly string $optionName,
        int $priority = 10,
    ) {
        parent::__construct("option_{$this->optionName}", $priority);
    }
}
