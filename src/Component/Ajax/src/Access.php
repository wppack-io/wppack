<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Ajax;

enum Access
{
    /** All users (registers both wp_ajax_ and wp_ajax_nopriv_). */
    case Public;

    /** Logged-in users only (registers wp_ajax_ only). */
    case Authenticated;

    /** Guest users only (registers wp_ajax_nopriv_ only). */
    case Guest;
}
