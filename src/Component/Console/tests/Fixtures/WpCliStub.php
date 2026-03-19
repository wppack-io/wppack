<?php

/**
 * Minimal WP_CLI stub for testing Console component.
 * This must be loaded via require_once BEFORE any class_exists() checks.
 *
 * Note: strict_types is intentionally omitted. Bracketed namespaces are required
 * because this file defines classes/functions in multiple namespaces.
 */

namespace {
    if (!class_exists('WP_CLI', false)) {
        class WP_CLI
        {
            /** @var list<array{command: string, callable: callable, args: array<string, mixed>}> */
            public static array $registeredCommands = [];

            /** @var list<string> */
            public static array $logged = [];

            /** @var list<string> */
            public static array $output = [];

            /** @var int|null */
            public static ?int $haltedCode = null;

            /** @var list<string> */
            public static array $successes = [];

            /** @var list<string> */
            public static array $errors = [];

            /** @var list<string> */
            public static array $warnings = [];

            public static function reset(): void
            {
                self::$registeredCommands = [];
                self::$logged = [];
                self::$output = [];
                self::$haltedCode = null;
                self::$successes = [];
                self::$errors = [];
                self::$warnings = [];
            }

            public static function add_command(string $name, callable $callable, array $args = []): void
            {
                self::$registeredCommands[] = [
                    'command' => $name,
                    'callable' => $callable,
                    'args' => $args,
                ];
            }

            public static function halt(int $code): void
            {
                self::$haltedCode = $code;
            }

            public static function out(string $message): void
            {
                self::$output[] = $message;
            }

            public static function log(string $message): void
            {
                self::$logged[] = $message;
            }

            public static function success(string $message): void
            {
                self::$successes[] = $message;
            }

            public static function error(string $message): void
            {
                self::$errors[] = $message;
            }

            public static function warning(string $message): void
            {
                self::$warnings[] = $message;
            }
        }
    }
}

namespace cli {
    if (!function_exists('cli\\confirm')) {
        function confirm(string $question, bool $default = false): bool
        {
            return $default;
        }
    }

    if (!function_exists('cli\\prompt')) {
        function prompt(string $question, ?string $default = null): string
        {
            return $default ?? '';
        }
    }
}

namespace WP_CLI\Utils {
    if (!function_exists('WP_CLI\\Utils\\format_items')) {
        /**
         * @param string                     $format
         * @param list<array<string, mixed>> $items
         * @param list<string>               $fields
         */
        function format_items(string $format, array $items, array $fields): void
        {
            // No-op stub for testing
        }
    }
}
