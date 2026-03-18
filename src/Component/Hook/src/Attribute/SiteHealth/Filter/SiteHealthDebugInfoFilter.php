<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\SiteHealth\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SiteHealthDebugInfoFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('site_health_debug_info', $priority);
    }
}
