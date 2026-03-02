<?php

declare(strict_types=1);

namespace WpPack\Component\Block\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RestPreInsertBlockFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('rest_pre_insert_block', $priority);
    }
}
