<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use Psr\Log\LoggerInterface;
use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'logger', priority: 100)]
final class LoggerDataCollector extends AbstractDataCollector
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'password', 'passwd', 'pwd', 'secret', 'token',
        'api_key', 'apikey', 'api-key',
        'authorization', 'auth',
        'private_key', 'access_token', 'refresh_token',
    ];

    private const MASKED_VALUE = '********';

    /** @var list<array{level: string, message: string, context: array<string, mixed>, timestamp: float, channel: string, file: string, line: int}> */
    private array $logs = [];

    private ?LoggerInterface $logger = null;

    public function __construct()
    {
        $this->registerHooks();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'logger';
    }

    public function getLabel(): string
    {
        return 'Logs';
    }

    /**
     * Record a log entry. Can be called by Logger handlers or other integrations.
     *
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        $file = '';
        $line = 0;
        if (isset($context['_file'])) {
            $file = (string) $context['_file'];
            unset($context['_file']);
        }
        if (isset($context['_line'])) {
            $line = (int) $context['_line'];
            unset($context['_line']);
        }

        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
            'channel' => $channel,
            'file' => $file,
            'line' => $line,
        ];
    }

    /**
     * Capture deprecated function/argument notices from WordPress.
     */
    public function captureDeprecation(string $function, string $replacement, string $version): void
    {
        $message = sprintf(
            '%s is deprecated since version %s. Use %s instead.',
            $function,
            $version,
            $replacement ?: 'an alternative',
        );

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $file = '';
        $line = 0;
        foreach ($trace as $frame) {
            $frameFile = $frame['file'] ?? '';
            if ($frameFile !== '' && !str_contains($frameFile, '/Debug/src/')) {
                $file = $frameFile;
                $line = $frame['line'] ?? 0;
                break;
            }
        }

        $context = [
            '_type' => 'deprecation',
            'type' => 'deprecation',
            'function' => $function,
            'replacement' => $replacement,
            'version' => $version,
            '_file' => $file,
            '_line' => $line,
        ];

        if ($this->logger !== null) {
            $this->logger->warning($message, $context);

            return;
        }

        $this->log('deprecation', $message, $context, 'wordpress');
    }

    /**
     * Capture deprecated hook notices from WordPress.
     */
    public function captureDeprecatedHook(string $hook, string $replacement, string $version, string $message): void
    {
        $logMessage = sprintf(
            'Hook %s is deprecated since version %s. %s',
            $hook,
            $version,
            $message ?: sprintf('Use %s instead.', $replacement ?: 'an alternative'),
        );

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $file = '';
        $line = 0;
        foreach ($trace as $frame) {
            $frameFile = $frame['file'] ?? '';
            if ($frameFile !== '' && !str_contains($frameFile, '/Debug/src/')) {
                $file = $frameFile;
                $line = $frame['line'] ?? 0;
                break;
            }
        }

        $context = [
            '_type' => 'deprecation',
            'type' => 'deprecated_hook',
            'hook' => $hook,
            'replacement' => $replacement,
            'version' => $version,
            '_file' => $file,
            '_line' => $line,
        ];

        if ($this->logger !== null) {
            $this->logger->warning($logMessage, $context);

            return;
        }

        $this->log('deprecation', $logMessage, $context, 'wordpress');
    }

    /**
     * Capture doing_it_wrong notices from WordPress.
     */
    public function captureDoingItWrong(string $function, string $message, string $version): void
    {
        $logMessage = sprintf(
            '%s was called incorrectly. %s (since version %s)',
            $function,
            $message,
            $version,
        );

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $file = '';
        $line = 0;
        foreach ($trace as $frame) {
            $frameFile = $frame['file'] ?? '';
            if ($frameFile !== '' && !str_contains($frameFile, '/Debug/src/')) {
                $file = $frameFile;
                $line = $frame['line'] ?? 0;
                break;
            }
        }

        $context = [
            '_type' => 'deprecation',
            'type' => 'doing_it_wrong',
            'function' => $function,
            'version' => $version,
            '_file' => $file,
            '_line' => $line,
        ];

        if ($this->logger !== null) {
            $this->logger->warning($logMessage, $context);

            return;
        }

        $this->log('deprecation', $logMessage, $context, 'wordpress');
    }

    public function collect(): void
    {
        $levelCounts = [];
        $deprecationCount = 0;
        foreach ($this->logs as $log) {
            $level = $log['level'];
            if (!isset($levelCounts[$level])) {
                $levelCounts[$level] = 0;
            }
            $levelCounts[$level]++;

            // Count deprecations from both direct 'deprecation' level and Logger-routed entries with _type
            if ($level === 'deprecation' || ($log['context']['_type'] ?? null) === 'deprecation') {
                $deprecationCount++;
            }
        }

        // Truncate message bodies and limit total entries
        $logs = array_slice($this->logs, 0, 200);
        foreach ($logs as &$log) {
            if (strlen($log['message']) > 1000) {
                $log['message'] = substr($log['message'], 0, 1000) . "\u{2026}";
            }
            // Remove sensitive context data
            $log['context'] = $this->maskSensitiveContext($log['context']);
        }
        unset($log);

        $this->data = [
            'logs' => $logs,
            'total_count' => count($this->logs),
            'level_counts' => $levelCounts,
            'deprecation_count' => $deprecationCount,
            'error_count' => ($levelCounts['error'] ?? 0) + ($levelCounts['critical'] ?? 0) + ($levelCounts['alert'] ?? 0) + ($levelCounts['emergency'] ?? 0),
        ];
    }

    public function getIndicatorValue(): string
    {
        $totalCount = $this->data['total_count'] ?? 0;

        return $totalCount > 0 ? (string) $totalCount : '';
    }

    public function getIndicatorColor(): string
    {
        $errorCount = $this->data['error_count'] ?? 0;
        $deprecationCount = $this->data['deprecation_count'] ?? 0;

        if ($errorCount > 0) {
            return 'red';
        }

        if ($deprecationCount > 0) {
            return 'yellow';
        }

        return 'green';
    }

    public function reset(): void
    {
        parent::reset();
        $this->logs = [];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function maskSensitiveContext(array $context): array
    {
        $masked = [];
        foreach ($context as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $masked[$key] = self::MASKED_VALUE;
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveContext($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($lower === $sensitive || str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('deprecated_function_run', [$this, 'captureDeprecation'], 10, 3);
        add_action('deprecated_argument_run', [$this, 'captureDeprecation'], 10, 3);
        add_action('deprecated_hook_run', [$this, 'captureDeprecatedHook'], 10, 4);
        add_action('doing_it_wrong_run', [$this, 'captureDoingItWrong'], 10, 3);
    }
}
