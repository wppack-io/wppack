<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Media\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpResourceHintsFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_resource_hints', $priority);
    }
}
