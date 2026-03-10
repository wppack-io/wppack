<?php

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

        $previous = $exception->getPrevious();
        while ($previous !== null) {
            $flat->chain[] = [
                'class' => $previous::class,
                'message' => $previous->getMessage(),
                'code' => $previous->getCode(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
                'trace' => self::buildTrace($previous),
            ];
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

        foreach ($exception->getTrace() as $frame) {
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
