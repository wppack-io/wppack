<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SiteHealthNavigationTabsFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('site_health_navigation_tabs', $priority);
    }
}
