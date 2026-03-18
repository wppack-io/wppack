<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Role\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class MapMetaCapFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('map_meta_cap', $priority);
    }
}
