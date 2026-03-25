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

namespace WpPack\Component\Debug\ErrorHandler;

/**
 * Captures the creation site of WP_Error instances via the wp_error_added action.
 *
 * Designed to be instantiated early (e.g. in a drop-in) so that WP_Error
 * objects created before the DI container boots are also tracked.
 */
final class WpErrorOriginCapture
{
    /** @var \WeakMap<\WP_Error, array{file: string, line: int, args: list<mixed>}> */
    private \WeakMap $origins;

    public function __construct()
    {
        $this->origins = new \WeakMap();
    }

    /**
     * Returns the early-registered instance from the drop-in, or creates a new one.
     */
    public static function fromGlobal(): self
    {
        return $GLOBALS['_wppack_wp_error_origin_capture'] ?? new self();
    }

    public function register(): void
    {
        add_action('wp_error_added', $this->capture(...), \PHP_INT_MAX, 4);
    }

    /** @return array{file: string, line: int, args: list<mixed>}|null */
    public function get(\WP_Error $error): ?array
    {
        return $this->origins[$error] ?? null;
    }

    /**
     * Capture the creation site of a WP_Error via the wp_error_added action.
     *
     * Only records the first error code added (i.e. the __construct call).
     */
    public function capture(string|int $code, string $message, mixed $data, \WP_Error $wpError): void
    {
        if (isset($this->origins[$wpError])) {
            return;
        }

        $backtrace = debug_backtrace(0, 10);

        // The WP_Error::__construct frame's file/line points to
        // where "new WP_Error(...)" was called in user code
        foreach ($backtrace as $frame) {
            if (
                ($frame['class'] ?? '') === \WP_Error::class
                && $frame['function'] === '__construct'
                && isset($frame['file'], $frame['line'])
            ) {
                $this->origins[$wpError] = [
                    'file' => $frame['file'],
                    'line' => $frame['line'],
                    'args' => $frame['args'] ?? [],
                ];

                return;
            }
        }
    }
}
