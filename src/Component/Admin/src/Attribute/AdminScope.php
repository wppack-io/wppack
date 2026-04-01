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

namespace WpPack\Component\Admin\Attribute;

/**
 * Determines where an admin page is registered.
 */
enum AdminScope: string
{
    /** Always register in the site admin (admin_menu). */
    case Site = 'site';

    /** Always register in the network admin (network_admin_menu). */
    case Network = 'network';

    /** Auto-detect based on plugin network activation status. */
    case Auto = 'auto';
}
