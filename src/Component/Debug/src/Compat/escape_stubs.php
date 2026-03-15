<?php

declare(strict_types=1);

/**
 * WordPress escape function stubs for standalone usage.
 *
 * Loaded only when WordPress is not available. Provides minimal
 * implementations using htmlspecialchars() for HTML/attribute escaping.
 *
 * @internal
 */

function esc_html(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_attr(mixed $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param list<string>|null $protocols */
function esc_url(mixed $url, ?array $protocols = null, string $_context = 'display'): string
{
    $url = (string) $url;
    if (preg_match('/^javascript:/i', $url)) {
        return '';
    }

    return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_js(mixed $text): string
{
    return str_replace(
        ["\\", '"', "'", "\n", "\r", '</'],
        ["\\\\", '\\"', "\\'", "\\n", "\\r", '<\\/'],
        (string) $text,
    );
}
