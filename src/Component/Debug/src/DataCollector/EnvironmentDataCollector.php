<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'environment', priority: 50)]
final class EnvironmentDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'environment';
    }

    public function getLabel(): string
    {
        return 'Environment';
    }

    public function collect(): void
    {
        $this->data = [
            'php' => $this->collectPhpInfo(),
            'extensions' => $this->collectExtensions(),
            'ini' => $this->collectIniSettings(),
            'opcache' => $this->collectOpcache(),
            'sapi' => PHP_SAPI,
            'os' => PHP_OS,
            'architecture' => PHP_INT_SIZE * 8,
            'hostname' => gethostname() ?: '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'major_minor' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            'zts' => \defined('ZEND_THREAD_SAFE') && ZEND_THREAD_SAFE,
            'debug' => \defined('ZEND_DEBUG_BUILD') && ZEND_DEBUG_BUILD,
            'gc_enabled' => gc_enabled(),
            'zend_version' => zend_version(),
        ];
    }

    /**
     * @return list<string>
     */
    private function collectExtensions(): array
    {
        $extensions = get_loaded_extensions();
        sort($extensions, SORT_NATURAL | SORT_FLAG_CASE);

        return $extensions;
    }

    /**
     * @return array<string, string>
     */
    private function collectIniSettings(): array
    {
        $keys = [
            'memory_limit',
            'max_execution_time',
            'max_input_time',
            'post_max_size',
            'upload_max_filesize',
            'max_file_uploads',
            'max_input_vars',
            'default_charset',
            'date.timezone',
            'display_errors',
            'error_reporting',
            'log_errors',
            'error_log',
            'session.gc_maxlifetime',
            'session.cookie_lifetime',
            'session.cookie_secure',
            'session.cookie_httponly',
            'realpath_cache_size',
            'realpath_cache_ttl',
            'allow_url_fopen',
            'disable_functions',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $value = ini_get($key);
            if ($value !== false) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectOpcache(): array
    {
        if (!\function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }

        /** @var array<string, mixed>|false $status */
        $status = @opcache_get_status(false);
        if ($status === false) {
            return ['enabled' => false];
        }

        /** @var array<string, mixed> $memory */
        $memory = $status['memory_usage'] ?? [];
        /** @var array<string, mixed> $stats */
        $stats = $status['opcache_statistics'] ?? [];

        $hits = (int) ($stats['hits'] ?? 0);
        $misses = (int) ($stats['misses'] ?? 0);
        $total = $hits + $misses;

        return [
            'enabled' => true,
            'jit' => (bool) ($status['jit']['enabled'] ?? false),
            'used_memory' => (int) ($memory['used_memory'] ?? 0),
            'free_memory' => (int) ($memory['free_memory'] ?? 0),
            'wasted_percentage' => round((float) ($memory['current_wasted_percentage'] ?? 0.0), 1),
            'cached_scripts' => (int) ($stats['num_cached_scripts'] ?? 0),
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 1) : 0.0,
            'oom_restarts' => (int) ($stats['oom_restarts'] ?? 0),
        ];
    }
}
