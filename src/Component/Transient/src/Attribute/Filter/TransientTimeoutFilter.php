<?php

declare(strict_types=1);

namespace WpPack\Component\Transient\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class TransientTimeoutFilter extends Filter
{
    public function __construct(
        public readonly string $name,
        int $priority = 10,
    ) {
        parent::__construct("expiration_of_transient_{$this->name}", $priority);
    }
}
