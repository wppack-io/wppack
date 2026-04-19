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

namespace WPPack\Component\Debug;

/**
 * Shared CSS custom property definitions for the Debug component.
 *
 * Used by both ToolbarAssets (scoped to #wppack-debug) and
 * ErrorRenderer (scoped to :root) to ensure a consistent design.
 */
final class CssTheme
{
    /**
     * Returns CSS custom property declarations (without a selector).
     *
     * The caller is responsible for wrapping them in the appropriate
     * selector (e.g. `#wppack-debug { ... }` or `:root { ... }`).
     */
    public static function cssVariables(): string
    {
        return <<<'CSS'
            /* Gray scale */
            --wpd-gray-900: #1f2937;
            --wpd-gray-800: #374151;
            --wpd-gray-700: #4b5563;
            --wpd-gray-600: #52525b;
            --wpd-gray-500: #6b7280;
            --wpd-gray-400: #9ca3af;
            --wpd-gray-300: #d1d5db;
            --wpd-gray-200: #e5e7eb;
            --wpd-gray-100: #f3f4f6;
            --wpd-gray-50: #fafafa;
            --wpd-white: #ffffff;

            /* Primary / accent */
            --wpd-primary: #3858e9;
            --wpd-primary-hover: #2d4ad6;
            --wpd-primary-a8: rgba(56, 88, 233, 0.08);

            /* Status colors */
            --wpd-green: #008a20;
            --wpd-green-a8: rgba(0, 138, 32, 0.08);
            --wpd-green-a10: rgba(0, 138, 32, 0.10);
            --wpd-green-a12: rgba(0, 138, 32, 0.12);
            --wpd-yellow: #996800;
            --wpd-yellow-a4: rgba(153, 104, 0, 0.04);
            --wpd-yellow-a6: rgba(153, 104, 0, 0.06);
            --wpd-yellow-a8: rgba(153, 104, 0, 0.08);
            --wpd-yellow-a10: rgba(153, 104, 0, 0.10);
            --wpd-yellow-a12: rgba(153, 104, 0, 0.12);
            --wpd-red: #cc1818;
            --wpd-red-a4: rgba(204, 24, 24, 0.04);
            --wpd-red-a8: rgba(204, 24, 24, 0.08);
            --wpd-red-a10: rgba(204, 24, 24, 0.10);
            --wpd-red-a12: rgba(204, 24, 24, 0.12);
            --wpd-orange: #b32d2e;
            --wpd-blue: #2563eb;
            --wpd-blue-a10: rgba(37, 99, 235, 0.10);
            --wpd-amber: #a16207;
            --wpd-amber-a10: rgba(161, 98, 7, 0.10);
            --wpd-dark-red: #990000;
            --wpd-dark-red-a12: rgba(153, 0, 0, 0.12);
            --wpd-gray-800-a8: rgba(55, 65, 81, 0.08);
            --wpd-gray-500-a10: rgba(107, 114, 128, 0.10);
            --wpd-purple: #7b2d8e;
            --wpd-purple-a8: rgba(130, 50, 150, 0.08);
            --wpd-rust: #9b3520;
            --wpd-rust-a8: rgba(160, 50, 30, 0.08);

            /* Typography */
            --wpd-font-sans: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            --wpd-font-mono: Menlo, Consolas, Monaco, 'Liberation Mono', 'Lucida Console', monospace;

            /* Radius */
            --wpd-radius: 8px;
            --wpd-radius-sm: 4px;
        CSS;
    }
}
