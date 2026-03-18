<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Transient\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class TransientFilter extends Filter
{
    public function __construct(
        public readonly string $name,
        int $priority = 10,
    ) {
        parent::__construct("transient_{$this->name}", $priority);
    }
}
