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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'wp_error', priority: 85)]
final class WpErrorDataCollector extends AbstractDataCollector
{
    /** @var \WeakMap<\WP_Error, array{file: string, line: int, args: list<mixed>}> */
    private \WeakMap $origins;

    /** @var list<array{code: string|int, message: string, data: mixed, object_id: int, timestamp: float, file: string, line: int, trace: list<array{file: string, line: int, function: string, class: string, type: string, args: list<mixed>}>}> */
    private array $errors = [];

    private bool $registered = false;

    public function __construct()
    {
        $this->origins = new \WeakMap();
    }

    /**
     * Returns the early-registered instance from the drop-in, or creates a new one.
     */
    public static function fromGlobal(): self
    {
        return $GLOBALS['_wppack_wp_error_collector'] ?? new self();
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;
        add_action('wp_error_added', $this->capture(...), \PHP_INT_MAX, 4);
    }

    /** @return array{file: string, line: int, args: list<mixed>}|null */
    public function getOrigin(\WP_Error $error): ?array
    {
        return $this->origins[$error] ?? null;
    }

    public function getName(): string
    {
        return 'wp_error';
    }

    public function getLabel(): string
    {
        return 'WP_Error';
    }

    public function capture(string|int $code, string $message, mixed $data, \WP_Error $wpError): void
    {
        $backtrace = debug_backtrace(0, 20);

        $file = '';
        $line = 0;
        $trace = [];
        $isConstructor = false;
        $constructorArgs = [];

        $skipInternal = true;
        foreach ($backtrace as $frame) {
            $frameClass = $frame['class'] ?? '';
            $frameFunction = $frame['function'];

            if ($skipInternal) {
                // Skip: self, call_user_func_array, WP_Hook, do_action
                if (
                    ($frameClass === self::class && $frameFunction === 'capture')
                    || ($frameClass === '' && $frameFunction === 'call_user_func_array')
                    || ($frameClass === \WP_Hook::class)
                    || ($frameClass === '' && $frameFunction === 'do_action')
                ) {
                    continue;
                }

                // Skip WP_Error internal frames, capturing file/line from last one
                if (
                    $frameClass === \WP_Error::class
                    && \in_array($frameFunction, ['__construct', 'add', 'add_data'], true)
                ) {
                    if (isset($frame['file'], $frame['line'])) {
                        $file = $frame['file'];
                        $line = $frame['line'];
                    }
                    if ($frameFunction === '__construct') {
                        $isConstructor = true;
                        $constructorArgs = $frame['args'] ?? [];
                    }
                    continue;
                }

                $skipInternal = false;

                // Fallback: if no WP_Error frame had file/line, use this frame
                if ($file === '') {
                    $file = $frame['file'] ?? '';
                    $line = $frame['line'] ?? 0;
                }
            }

            $trace[] = [
                'file' => $frame['file'] ?? '',
                'line' => $frame['line'] ?? 0,
                'function' => $frameFunction,
                'class' => $frameClass,
                'type' => $frame['type'] ?? '',
                'args' => $frame['args'] ?? [],
            ];
        }

        // Record origin for constructor calls only (first event per WP_Error)
        if ($isConstructor && !isset($this->origins[$wpError])) {
            $this->origins[$wpError] = [
                'file' => $file,
                'line' => $line,
                'args' => $constructorArgs,
            ];
        }

        $this->errors[] = [
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'object_id' => spl_object_id($wpError),
            'timestamp' => microtime(true),
            'file' => $file,
            'line' => $line,
            'trace' => $trace,
        ];
    }

    public function collect(): void
    {
        $uniqueObjects = [];
        $errors = [];

        foreach ($this->errors as $error) {
            $uniqueObjects[$error['object_id']] = true;

            $errors[] = [
                'code' => $error['code'],
                'message' => $error['message'],
                'data' => $this->formatData($error['data']),
                'object_id' => $error['object_id'],
                'timestamp' => $error['timestamp'],
                'file' => $this->shortenPath($error['file']),
                'line' => $error['line'],
                'trace' => $this->formatTrace($error['trace']),
            ];
        }

        $this->data = [
            'errors' => $errors,
            'total_count' => \count($errors),
            'unique_objects' => \count($uniqueObjects),
        ];
    }

    public function getIndicatorValue(): string
    {
        $total = $this->data['total_count'] ?? 0;

        return $total > 0 ? (string) $total : '';
    }

    public function getIndicatorColor(): string
    {
        $total = $this->data['total_count'] ?? 0;

        return $total > 0 ? 'yellow' : 'default';
    }

    public function reset(): void
    {
        parent::reset();
        $this->errors = [];
        $this->origins = new \WeakMap();
    }

    private function formatData(mixed $data): string
    {
        return match (true) {
            $data === null, $data === '' => '(none)',
            is_bool($data) => $data ? 'true' : 'false',
            is_int($data), is_float($data) => (string) $data,
            is_string($data) => mb_strlen($data) > 200 ? mb_substr($data, 0, 200) . '...' : $data,
            is_array($data) => 'array(' . \count($data) . ')',
            is_object($data) => $data::class,
            default => get_debug_type($data),
        };
    }

    private function shortenPath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (\defined('ABSPATH') && str_starts_with($path, ABSPATH)) {
            return substr($path, \strlen(ABSPATH));
        }

        $vendorPos = strpos($path, '/vendor/');
        if ($vendorPos !== false) {
            return '...' . substr($path, $vendorPos);
        }

        return $path;
    }

    /**
     * @param list<array{file: string, line: int, function: string, class: string, type: string, args: list<mixed>}> $trace
     * @return list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>}>
     */
    private function formatTrace(array $trace): array
    {
        $result = [];
        foreach ($trace as $frame) {
            $args = [];
            foreach ($frame['args'] as $arg) {
                $args[] = self::formatArg($arg);
            }
            $frame['file'] = $this->shortenPath($frame['file']);
            $frame['args'] = $args;
            $result[] = $frame;
        }

        return $result;
    }

    private static function formatArg(mixed $arg): string
    {
        return match (true) {
            is_null($arg) => 'null',
            is_bool($arg) => $arg ? 'true' : 'false',
            is_int($arg), is_float($arg) => (string) $arg,
            is_string($arg) => \strlen($arg) > 50 ? '"' . substr($arg, 0, 50) . '..."' : '"' . $arg . '"',
            is_array($arg) => 'array(' . \count($arg) . ')',
            is_object($arg) => $arg::class,
            is_resource($arg) => 'resource(' . get_resource_type($arg) . ')',
            default => '?',
        };
    }
}
