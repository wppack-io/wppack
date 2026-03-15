<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Compat;

/**
 * Ensures WordPress escape functions are available for standalone usage.
 *
 * When running outside WordPress (e.g., demo scripts, standalone tools),
 * the Escaper component requires esc_html(), esc_attr(), esc_url(), and esc_js().
 * This class registers fallback stubs using htmlspecialchars() if WordPress
 * has not already defined them.
 *
 * Call EscapeFunctions::ensure() before creating a PhpRenderer that uses the
 * default Escaper.
 */
final class EscapeFunctions
{
    private static bool $registered = false;

    public static function ensure(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        if (!function_exists('esc_html')) {
            require __DIR__ . '/escape_stubs.php';
        }
    }
}
