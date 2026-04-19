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

namespace WPPack\Component\Sanitizer;

/**
 * WordPress sanitization functions wrapper.
 *
 * Provides type-safe access to WordPress input sanitization functions.
 * Each method wraps a WordPress sanitize function, ensuring that all
 * registered WordPress filters are applied.
 */
final class Sanitizer
{
    /**
     * Sanitize a text field value.
     *
     * Removes HTML tags, validates UTF-8, and strips extra whitespace (single line).
     *
     * @see sanitize_text_field()
     */
    public function text(string $value): string
    {
        return sanitize_text_field($value);
    }

    /**
     * Sanitize a textarea field value.
     *
     * Same as text() but preserves newlines (for multi-line input).
     *
     * @see sanitize_textarea_field()
     */
    public function textarea(string $value): string
    {
        return sanitize_textarea_field($value);
    }

    /**
     * Filter content to keep only HTML tags safe for post content.
     *
     * Removes dangerous tags like <script>, <form>, etc. while keeping
     * safe tags allowed in post content.
     *
     * @see wp_kses_post()
     */
    public function ksesPost(string $value): string
    {
        return wp_kses_post($value);
    }

    /**
     * Filter content to keep only specified HTML tags.
     *
     * @param string|array<string, array<string, bool>> $allowedHtml Context string ('post', 'strip') or allowed tags array
     *
     * @see wp_kses()
     */
    public function kses(string $value, string|array $allowedHtml): string
    {
        return wp_kses($value, $allowedHtml);
    }

    /**
     * Strip all HTML tags from a string.
     *
     * Removes all HTML tags including the contents of <script> and <style> tags.
     * Safer than PHP's strip_tags().
     *
     * @see wp_strip_all_tags()
     */
    public function stripTags(string $value): string
    {
        return wp_strip_all_tags($value);
    }

    /**
     * Sanitize an email address.
     *
     * Strips characters not allowed in email addresses.
     *
     * @see sanitize_email()
     */
    public function email(string $value): string
    {
        return sanitize_email($value);
    }

    /**
     * Sanitize a URL for safe use (database storage, redirects).
     *
     * Validates URL scheme and removes invalid characters.
     * No HTML entity encoding (use Escaper::url() for HTML output).
     *
     * @see esc_url_raw()
     */
    public function url(string $value): string
    {
        return esc_url_raw($value);
    }

    /**
     * Sanitize a file name.
     *
     * Removes special characters and replaces spaces with dashes.
     *
     * @see sanitize_file_name()
     */
    public function filename(string $value): string
    {
        return sanitize_file_name($value);
    }

    /**
     * Sanitize a key string.
     *
     * Restricts to lowercase alphanumeric characters, dashes, and underscores.
     *
     * @see sanitize_key()
     */
    public function key(string $value): string
    {
        return sanitize_key($value);
    }

    /**
     * Sanitize a title for use as a slug.
     *
     * Converts to URL-safe string, removes accents.
     *
     * @see sanitize_title()
     */
    public function title(string $value): string
    {
        return sanitize_title($value);
    }

    /**
     * Sanitize a title with dashes.
     *
     * Same as title() plus converts spaces to dashes.
     *
     * @see sanitize_title_with_dashes()
     */
    public function slug(string $value): string
    {
        return sanitize_title_with_dashes($value);
    }

    /**
     * Sanitize an HTML class name.
     *
     * Restricts to A-Z, a-z, 0-9, underscores, and dashes.
     *
     * @see sanitize_html_class()
     */
    public function htmlClass(string $value): string
    {
        return sanitize_html_class($value);
    }

    /**
     * Sanitize a username.
     *
     * Strips characters not allowed in usernames.
     *
     * @see sanitize_user()
     */
    public function user(string $value, bool $strict = false): string
    {
        return sanitize_user($value, $strict);
    }

    /**
     * Sanitize a MIME type string.
     *
     * Validates MIME type format (e.g., image/png).
     *
     * @see sanitize_mime_type()
     */
    public function mimeType(string $value): string
    {
        return sanitize_mime_type($value);
    }

    /**
     * Sanitize a hex color value.
     *
     * Validates #RRGGBB or #RGB format. Returns empty string if invalid.
     *
     * @see sanitize_hex_color()
     */
    public function hexColor(string $value): string
    {
        return sanitize_hex_color($value) ?? '';
    }
}
