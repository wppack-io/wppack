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

final class FlattenException
{
    /** @var list<array{class: string, message: string, code: int, file: string, line: int, trace: list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}>}> */
    private array $chain = [];

    private function __construct(
        private readonly string $class,
        private readonly string $message,
        private readonly int $code,
        private readonly string $file,
        private readonly int $line,
        private readonly int $statusCode,
        /** @var list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}> */
        private readonly array $trace,
    ) {}

    public static function createFromThrowable(\Throwable $exception): self
    {
        $statusCode = 500;
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        }

        $class = $exception::class;
        if (method_exists($exception, 'getDisplayClass')) {
            $class = $exception->getDisplayClass();
        }

        $trace = self::buildTrace($exception);

        $flat = new self(
            class: $class,
            message: $exception->getMessage(),
            code: $exception->getCode(),
            file: $exception->getFile(),
            line: $exception->getLine(),
            statusCode: $statusCode,
            trace: $trace,
        );

        // Build exception chain
        $flat->chain[] = [
            'class' => $class,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ];

        $previousTrace = $trace;
        $previous = $exception->getPrevious();
        while ($previous !== null) {
            $currentTrace = self::buildTrace($previous);
            $trimmedTrace = self::trimCommonFrames($currentTrace, $previousTrace);
            $previousClass = method_exists($previous, 'getDisplayClass')
                ? $previous->getDisplayClass()
                : $previous::class;
            $flat->chain[] = [
                'class' => $previousClass,
                'message' => $previous->getMessage(),
                'code' => $previous->getCode(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
                'trace' => $trimmedTrace,
            ];
            $previousTrace = $currentTrace;
            $previous = $previous->getPrevious();
        }

        return $flat;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** @return list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}> */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /** @return list<array{class: string, message: string, code: int, file: string, line: int, trace: list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}>}> */
    public function getChain(): array
    {
        return $this->chain;
    }

    /**
     * @return list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}>
     */
    private static function buildTrace(\Throwable $exception): array
    {
        $result = [];

        // Prepend the throw location as frame #0, unless the first trace
        // frame already points to the same file:line (avoids duplicate frames
        // when file/line was overridden to match the call site, e.g. wp_die)
        $throwFile = $exception->getFile();
        $throwLine = $exception->getLine();
        $traceFrames = $exception->getTrace();
        $firstFrame = $traceFrames[0] ?? null;
        $skipThrowFrame = $firstFrame !== null
            && ($firstFrame['file'] ?? '') === $throwFile
            && ($firstFrame['line'] ?? 0) === $throwLine;

        if (!$skipThrowFrame) {
            $throwContext = [];
            $throwHighlight = 0;
            if ($throwFile !== '' && $throwLine > 0 && is_file($throwFile) && is_readable($throwFile)) {
                $throwContext = self::getCodeContext($throwFile, $throwLine, 10);
                $throwHighlight = $throwLine;
            }

            // Build synthetic constructor args from exception properties
            // so frame #0 shows e.g. RuntimeException->__construct("msg", 0, PDOException)
            // Args are pre-formatted because the throw frame is not processed by the trace loop
            $throwArgs = [self::formatArg($exception->getMessage())];
            $hasCode = $exception->getCode() !== 0;
            $hasPrevious = $exception->getPrevious() !== null;
            if ($hasCode || $hasPrevious) {
                $throwArgs[] = self::formatArg($exception->getCode());
            }
            if ($hasPrevious) {
                $throwArgs[] = self::formatArg($exception->getPrevious());
            }

            $result[] = [
                'file' => $throwFile,
                'line' => $throwLine,
                'function' => '__construct',
                'class' => $exception::class,
                'type' => '->',
                'args' => $throwArgs,
                'code_context' => $throwContext,
                'highlight_line' => $throwHighlight,
            ];
        }

        foreach ($traceFrames as $frame) {
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? 0;
            $function = $frame['function'];
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';

            $args = [];
            foreach ($frame['args'] ?? [] as $arg) {
                $args[] = self::formatArg($arg);
            }

            $codeContext = [];
            $highlightLine = 0;
            if ($file !== '' && $line > 0 && is_file($file) && is_readable($file)) {
                $codeContext = self::getCodeContext($file, $line, 10);
                $highlightLine = $line;
            }

            $result[] = [
                'file' => $file,
                'line' => $line,
                'function' => $function,
                'class' => $class,
                'type' => $type,
                'args' => $args,
                'code_context' => $codeContext,
                'highlight_line' => $highlightLine,
            ];
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private static function getCodeContext(string $file, int $line, int $context): array
    {
        $lines = @file($file);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $line - $context - 1);
        $end = min(count($lines), $line + $context);

        $result = [];
        for ($i = $start; $i < $end; $i++) {
            $result[] = rtrim($lines[$i]);
        }

        return $result;
    }

    /**
     * Remove trailing frames from $trace that are identical to trailing frames in $parentTrace.
     *
     * @param list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}> $trace
     * @param list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}> $parentTrace
     *
     * @return list<array{file: string, line: int, function: string, class: string, type: string, args: list<string>, code_context: list<string>, highlight_line: int}>
     */
    private static function trimCommonFrames(array $trace, array $parentTrace): array
    {
        $ti = count($trace) - 1;
        $pi = count($parentTrace) - 1;

        while ($ti >= 0 && $pi >= 0) {
            if (
                $trace[$ti]['file'] !== $parentTrace[$pi]['file']
                || $trace[$ti]['line'] !== $parentTrace[$pi]['line']
                || $trace[$ti]['function'] !== $parentTrace[$pi]['function']
                || $trace[$ti]['class'] !== $parentTrace[$pi]['class']
            ) {
                break;
            }
            --$ti;
            --$pi;
        }

        return \array_slice($trace, 0, $ti + 1);
    }

    private static function formatArg(mixed $arg): string
    {
        return match (true) {
            is_null($arg) => 'null',
            is_bool($arg) => $arg ? 'true' : 'false',
            is_int($arg), is_float($arg) => (string) $arg,
            is_string($arg) => strlen($arg) > 50 ? '"' . substr($arg, 0, 50) . '..."' : '"' . $arg . '"',
            is_array($arg) => 'array(' . count($arg) . ')',
            is_object($arg) => $arg::class,
            is_resource($arg) => 'resource(' . get_resource_type($arg) . ')',
            default => '?',
        };
    }
}
