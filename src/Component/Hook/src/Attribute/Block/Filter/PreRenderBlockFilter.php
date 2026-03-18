<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Block\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreRenderBlockFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('pre_render_block', $priority);
    }
}
