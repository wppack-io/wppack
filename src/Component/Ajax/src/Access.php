<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax;

enum Access
{
    /** All users (registers both wp_ajax_ and wp_ajax_nopriv_). */
    case Public;

    /** Logged-in users only (registers wp_ajax_ only). */
    case Authenticated;

    /** Guest users only (registers wp_ajax_nopriv_ only). */
    case Guest;
}
