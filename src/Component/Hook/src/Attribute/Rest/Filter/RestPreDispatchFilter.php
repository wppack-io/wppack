<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Rest\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RestPreDispatchFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('rest_pre_dispatch', $priority);
    }
}
