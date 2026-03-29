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

namespace WpPack\Component\Logger;

/**
 * Captures error_log() output by redirecting to a per-request temporary file.
 *
 * Replaces the PHP error_log ini directive with a temp file, reads it on
 * collect(), and feeds entries via listeners. The temp file isolates each
 * request's output, avoiding concurrency issues with shared log files.
 *
 * Singleton: use getInstance() or create() to obtain the shared instance.
 */
final class ErrorLogInterceptor
{
    private static ?self $instance = null;

    private bool $registered = false;

    private ?string $originalErrorLog = null;

    private ?string $tempFile = null;

    /** @var list<callable(string, string): void> */
    private array $listeners = [];

    private bool $collecting = false;

    public function __construct() {}

    /**
     * Set LoggerFactory to feed captured entries into the Logger pipeline.
     */
    public function setLoggerFactory(LoggerFactory $loggerFactory): void
    {
        $this->addListener(static function (string $level, string $message) use ($loggerFactory): void {
            $loggerFactory->create('error_log')->log($level, $message, [
                '_source' => 'error_log',
                '_file' => '',
                '_line' => 0,
            ]);
        });
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Get or create the singleton instance.
     */
    public static function create(): self
    {
        return self::$instance ??= new self();
    }

    public function register(): void
    {
        if ($this->registered) {
            // Re-register: update error_log redirect (e.g., after wp_debug_mode overwrites it)
            $this->originalErrorLog = ini_get('error_log') ?: '';
            ini_set('error_log', $this->tempFile ?? '');

            return;
        }

        self::$instance = $this;

        $this->originalErrorLog = ini_get('error_log') ?: '';

        $tempFile = @tempnam(sys_get_temp_dir(), 'wppack_errlog_');
        if ($tempFile === false) {
            return;
        }

        $this->tempFile = $tempFile;
        ini_set('error_log', $this->tempFile);
        $this->registered = true;

        register_shutdown_function(function (): void {
            $this->collect();
            $this->restore();
        });
    }

    public function restore(): void
    {
        if (!$this->registered) {
            return;
        }

        ini_set('error_log', $this->originalErrorLog ?? '');

        if ($this->tempFile !== null && file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }

        $this->originalErrorLog = null;
        $this->tempFile = null;
        $this->registered = false;
    }

    /**
     * Add a listener that receives captured error_log messages.
     *
     * @param callable(string, string): void $listener Receives (level, message)
     */
    public function addListener(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Read entries from the temporary log file.
     */
    public function collect(): void
    {
        if (!$this->registered || $this->tempFile === null || $this->collecting) {
            return;
        }

        if (!is_file($this->tempFile)) {
            return;
        }

        clearstatcache(true, $this->tempFile);
        $content = @file_get_contents($this->tempFile);

        if (!\is_string($content) || $content === '') {
            return;
        }

        // Truncate to avoid re-reading on next collect()
        @file_put_contents($this->tempFile, '');

        $this->collecting = true;

        try {
            $entries = $this->parseEntries($content);

            foreach ($entries as [$level, $message]) {
                foreach ($this->listeners as $listener) {
                    $listener($level, $message);
                }
            }
        } finally {
            $this->collecting = false;
        }
    }

    public function getTempFile(): ?string
    {
        return $this->tempFile;
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Parse multi-line error log content into individual entries.
     *
     * @return list<array{string, string}> List of [level, message] pairs
     */
    private function parseEntries(string $content): array
    {
        $lines = explode("\n", $content);
        $entries = [];
        $currentEntry = '';

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (preg_match('/^\[.+?\]/', $line)) {
                if ($currentEntry !== '') {
                    $entries[] = $this->parseMessage($currentEntry);
                }
                $currentEntry = $line;
            } else {
                $currentEntry .= "\n" . $line;
            }
        }

        if ($currentEntry !== '') {
            $entries[] = $this->parseMessage($currentEntry);
        }

        return $entries;
    }

    /**
     * Parse a single error_log entry to extract level and clean message.
     *
     * @return array{string, string} [level, message]
     */
    private function parseMessage(string $message): array
    {
        if (preg_match('/^\[.+?\]\s+PHP\s+(Fatal error|Parse error|Warning|Notice|Deprecated|Strict Standards):\s*(.+)$/s', $message, $matches)) {
            $level = match (strtolower($matches[1])) {
                'fatal error', 'parse error' => 'critical',
                'warning' => 'warning',
                default => 'notice',
            };

            return [$level, $matches[2]];
        }

        if (preg_match('/^\[.+?\]\s+(.+)$/s', $message, $matches)) {
            return ['debug', $matches[1]];
        }

        return ['debug', $message];
    }
}
