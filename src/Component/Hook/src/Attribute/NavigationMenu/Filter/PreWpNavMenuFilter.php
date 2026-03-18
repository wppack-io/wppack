<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\NavigationMenu\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreWpNavMenuFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('pre_wp_nav_menu', $priority);
    }
}
