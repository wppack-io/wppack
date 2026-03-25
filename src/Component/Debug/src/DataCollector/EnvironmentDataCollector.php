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
            'server' => $this->collectServerInfo(),
            'runtime' => $this->collectRuntime(),
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
    private function collectServerInfo(): array
    {
        $software = $_SERVER['SERVER_SOFTWARE'] ?? '';

        return [
            'software' => $software,
            'web_server' => $this->parseWebServer($software),
            'name' => $_SERVER['SERVER_NAME'] ?? '',
            'addr' => $_SERVER['SERVER_ADDR'] ?? '',
            'port' => $_SERVER['SERVER_PORT'] ?? '',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        ];
    }

    /**
     * @return array{name: string, version: string, raw: string}
     */
    private function parseWebServer(string $software): array
    {
        // PHP built-in development server
        if (PHP_SAPI === 'cli-server') {
            return ['name' => 'PHP Built-in', 'version' => PHP_VERSION, 'raw' => 'PHP Built-in Server'];
        }

        if ($software === '') {
            return ['name' => '', 'version' => '', 'raw' => ''];
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9_.-]*)(?:\/(\S+))?/', $software, $m)) {
            return [
                'name' => ucfirst(strtolower($m[1])),
                'version' => $m[2] ?? '',
                'raw' => $software,
            ];
        }

        return ['name' => $software, 'version' => '', 'raw' => $software];
    }

    /**
     * @return array{type: string, details: array<string, string>}
     */
    private function collectRuntime(): array
    {
        // Lambda
        $functionName = $this->getEnv('AWS_LAMBDA_FUNCTION_NAME');
        if ($functionName !== '') {
            return [
                'type' => 'lambda',
                'details' => array_filter([
                    'Function' => $functionName,
                    'Memory' => $this->getEnv('AWS_LAMBDA_FUNCTION_MEMORY_SIZE'),
                    'Region' => $this->getEnv('AWS_REGION'),
                    'Runtime' => $this->getEnv('AWS_EXECUTION_ENV'),
                    'Handler' => $this->getEnv('_HANDLER'),
                ]),
            ];
        }

        // ECS
        $ecsMetadata = $this->getEnv('ECS_CONTAINER_METADATA_URI_V4');
        if ($ecsMetadata === '') {
            $ecsMetadata = $this->getEnv('ECS_CONTAINER_METADATA_URI');
        }
        if ($ecsMetadata !== '') {
            $launchType = '';
            $ecsExecEnv = $this->getEnv('AWS_EXECUTION_ENV');
            if (str_contains($ecsExecEnv, 'FARGATE')) {
                $launchType = 'Fargate';
            } elseif ($ecsExecEnv !== '') {
                $launchType = 'EC2';
            }

            return [
                'type' => 'ecs',
                'details' => array_filter([
                    'Launch Type' => $launchType,
                    'Region' => $this->getEnv('AWS_REGION'),
                ]),
            ];
        }

        // Kubernetes
        $k8sHost = $this->getEnv('KUBERNETES_SERVICE_HOST');
        if ($k8sHost !== '') {
            return [
                'type' => 'kubernetes',
                'details' => array_filter([
                    'Namespace' => $this->getEnv('POD_NAMESPACE') ?: $this->readFileContent('/var/run/secrets/kubernetes.io/serviceaccount/namespace'),
                    'Node' => $this->getEnv('NODE_NAME'),
                    'Pod' => $this->getEnv('POD_NAME') ?: $this->getEnv('HOSTNAME'),
                ]),
            ];
        }

        // Docker (not in ECS/K8s)
        if ($this->isDocker()) {
            return [
                'type' => 'docker',
                'details' => array_filter([
                    'Hostname' => $this->getEnv('HOSTNAME'),
                ]),
            ];
        }

        // EC2
        if ($this->isEc2()) {
            return [
                'type' => 'ec2',
                'details' => array_filter([
                    'Region' => $this->getEnv('AWS_REGION'),
                ]),
            ];
        }

        return ['type' => '', 'details' => []];
    }

    private function getEnv(string $name): string
    {
        $value = getenv($name);

        return $value !== false ? $value : '';
    }

    private function readFileContent(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        $content = @file_get_contents($path);

        return $content !== false ? trim($content) : '';
    }

    private function isDocker(): bool
    {
        return is_file('/.dockerenv');
    }

    private function isEc2(): bool
    {
        $uuid = $this->readFileContent('/sys/devices/virtual/dmi/id/product_uuid');
        if ($uuid !== '' && str_starts_with(strtolower($uuid), 'ec2')) {
            return true;
        }

        $boardAssetTag = $this->readFileContent('/sys/devices/virtual/dmi/id/board_asset_tag');

        return str_starts_with($boardAssetTag, 'i-');
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
