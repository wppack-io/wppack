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

namespace WpPack\Component\Handler;

class Configuration
{
    /** @var array<string, mixed> */
    private readonly array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['web_root'])) {
            $cwd = getcwd();

            if ($cwd === false) {
                throw new \RuntimeException('Unable to determine current working directory.');
            }

            $config['web_root'] = $cwd;
        }

        $this->config = $this->normalizeConfig($config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!\is_array($value) || !isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        // Normalize Lambda configuration
        if (isset($config['lambda'])) {
            $config['lambda'] = match (true) {
                $config['lambda'] === true => [
                    'enabled' => true,
                    'directories' => ['/tmp/uploads', '/tmp/cache', '/tmp/sessions'],
                ],
                $config['lambda'] === false => ['enabled' => false],
                default => $config['lambda'],
            };
        } else {
            $config['lambda'] = [];
        }

        // Normalize Multisite configuration
        if (isset($config['multisite'])) {
            $config['multisite'] = match (true) {
                $config['multisite'] === true => [
                    'enabled' => true,
                    'pattern' => '#^/[_0-9a-zA-Z-]+(/wp-.*)#',
                    'replacement' => '/wp$1',
                ],
                $config['multisite'] === false => ['enabled' => false],
                default => $config['multisite'],
            };
        } else {
            $config['multisite'] = ['enabled' => false];
        }

        // Security configuration
        $defaultSecurity = [
            'allow_directory_listing' => false,
            'check_symlinks' => true,
            'blocked_patterns' => [
                '/\.git/',
                '/\.env/',
                '/\.htaccess/',
                '/composer\.(json|lock)/',
                '/wp-config\.php/',
                '/readme\.(txt|html|md)/i',
            ],
        ];

        $config['security'] = isset($config['security'])
            ? array_merge($defaultSecurity, $config['security'])
            : $defaultSecurity;

        // Other defaults
        $defaults = [
            'wordpress_index' => '/index.php',
            'wp_directory' => '/wp',
            'index_files' => ['index.php', 'index.html', 'index.htm'],
        ];

        return array_merge($defaults, $config);
    }
}
