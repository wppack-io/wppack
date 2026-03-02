<?php

declare(strict_types=1);

namespace WpPack\Component\Block\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RenderBlockDataFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('render_block_data', $priority);
    }
}
