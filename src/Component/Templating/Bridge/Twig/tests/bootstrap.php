<?php

declare(strict_types=1);

$rootAutoload = __DIR__ . '/../../../../../vendor/autoload.php';
$componentAutoload = __DIR__ . '/../vendor/autoload.php';

if (file_exists($rootAutoload)) {
    require_once $rootAutoload;
} elseif (file_exists($componentAutoload)) {
    require_once $componentAutoload;
}

// Provide WordPress escape function stubs for unit tests without WordPress
if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        if (preg_match('/^javascript:/i', $url)) {
            return '';
        }

        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('esc_js')) {
    function esc_js(string $text): string
    {
        return str_replace(
            ["\\", '"', "'", "\n", "\r", '</'],
            ["\\\\", '\\"', "\\'", "\\n", "\\r", '<\\/'],
            $text,
        );
    }
}
