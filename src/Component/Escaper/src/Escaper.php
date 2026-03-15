<?php

declare(strict_types=1);

namespace WpPack\Component\Escaper;

/**
 * WordPress output escaping functions wrapper.
 *
 * Provides type-safe access to WordPress escape functions for safe output
 * in various contexts (HTML, attributes, URLs, JavaScript).
 */
final class Escaper
{
    /**
     * Escape a string for safe output in HTML context.
     *
     * Converts special characters to HTML entities (&, <, >, ", ').
     *
     * @see esc_html()
     */
    public function html(string $value): string
    {
        return esc_html($value);
    }

    /**
     * Escape a string for safe output in an HTML attribute value.
     *
     * Converts special characters to HTML entities, safe for use
     * inside attribute values (e.g., <input value="...">>).
     *
     * @see esc_attr()
     */
    public function attr(string $value): string
    {
        return esc_attr($value);
    }

    /**
     * Escape a URL for safe output in HTML.
     *
     * Validates URL scheme, removes invalid characters, and encodes
     * HTML entities (& → &amp;). For database storage or redirects,
     * use Sanitizer::url() instead.
     *
     * @see esc_url()
     */
    public function url(string $value): string
    {
        return esc_url($value);
    }

    /**
     * Escape a string for safe output in inline JavaScript.
     *
     * Escapes quotes, backslashes, and other characters that could
     * break out of a JavaScript string context.
     *
     * @see esc_js()
     */
    public function js(string $value): string
    {
        return esc_js($value);
    }

    /**
     * Escape a string using the specified strategy.
     *
     * @param string $strategy One of: html, attr, url, js
     */
    public function escape(string $value, string $strategy = 'html'): string
    {
        return match ($strategy) {
            'html' => $this->html($value),
            'attr' => $this->attr($value),
            'url' => $this->url($value),
            'js' => $this->js($value),
            default => throw new \InvalidArgumentException(
                sprintf('Unknown escaping strategy "%s". Supported: html, attr, url, js.', $strategy),
            ),
        };
    }
}
