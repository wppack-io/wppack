<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\NavigationMenu\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NavMenuItemIdFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('nav_menu_item_id', $priority);
    }
}
