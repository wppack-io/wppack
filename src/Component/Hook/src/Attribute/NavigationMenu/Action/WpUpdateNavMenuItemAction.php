<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\NavigationMenu\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpUpdateNavMenuItemAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_update_nav_menu_item', $priority);
    }
}
