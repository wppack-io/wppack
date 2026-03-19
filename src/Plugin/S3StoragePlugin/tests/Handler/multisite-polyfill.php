<?php

declare(strict_types=1);

/**
 * Polyfill multisite functions for single-site test environments.
 *
 * These functions are normally defined in wp-includes/ms-blogs.php,
 * which is only loaded in multisite installations.
 */

if (!\function_exists('switch_to_blog')) {
    /**
     * @param int $new_blog_id
     * @param bool $deprecated
     * @return true
     */
    function switch_to_blog(int $new_blog_id, bool $deprecated = false): true
    {
        return true;
    }
}

if (!\function_exists('restore_current_blog')) {
    /**
     * @return bool
     */
    function restore_current_blog(): bool
    {
        return true;
    }
}
