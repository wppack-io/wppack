<?php

declare(strict_types=1);

namespace WpPack\Component\NavigationMenu\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpSetupNavMenuItemFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_setup_nav_menu_item', $priority);
    }
}
