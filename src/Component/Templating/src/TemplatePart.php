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

namespace WpPack\Component\Templating;

/**
 * Wrapper around WordPress get_template_part().
 *
 * Provides a capture method for getting template part output as a string.
 */
final class TemplatePart
{
    /**
     * Render a WordPress template part (direct output).
     *
     * @param array<string, mixed> $args Additional arguments passed to the template
     *
     * @see get_template_part()
     */
    public static function render(string $slug, string $name = '', array $args = []): void
    {
        get_template_part($slug, $name, $args);
    }

    /**
     * Capture a WordPress template part output as a string.
     *
     * @param array<string, mixed> $args Additional arguments passed to the template
     *
     * @see get_template_part()
     */
    public static function capture(string $slug, string $name = '', array $args = []): string
    {
        ob_start();
        get_template_part($slug, $name, $args);

        return ob_get_clean() ?: '';
    }
}
