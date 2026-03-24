<?php

/**
 * WpPack Object Cache Drop-in
 *
 * Copy this file to wp-content/object-cache.php.
 *
 * Configuration (wp-config.php):
 *   define('WPPACK_CACHE_DSN', 'redis://127.0.0.1:6379');
 *   define('WPPACK_CACHE_PREFIX', 'wp:');           // optional, default 'wp:'
 *   define('WPPACK_CACHE_MAX_TTL', 86400);             // optional, max TTL in seconds
 *   define('WPPACK_CACHE_OPTIONS', ['timeout' => 5]); // optional
 *
 * @package wppack/cache
 */

declare(strict_types=1);

use WpPack\Component\Cache\Adapter\Adapter;
use WpPack\Component\Cache\Adapter\AdapterInterface;
use WpPack\Component\Cache\ObjectCache;
use WpPack\Component\Cache\Strategy\AllOptionsSplitStrategy;
use WpPack\Component\Cache\Strategy\NotOptionsSplitStrategy;
use WpPack\Component\Cache\Strategy\SiteNotOptionsSplitStrategy;
use WpPack\Component\Cache\Strategy\SiteOptionsSplitStrategy;

// Locate and load Composer autoloader.
// Wrapped in an IIFE to avoid leaking variables into the global scope.
(static function (): void {
    $candidates = [
        // Bedrock / standard vendor in project root
        \dirname(ABSPATH) . '/vendor/autoload.php',
        // Standard WordPress layout
        ABSPATH . 'vendor/autoload.php',
        // wp-content vendor
        WP_CONTENT_DIR . '/vendor/autoload.php',
    ];

    foreach ($candidates as $autoload) {
        if (file_exists($autoload)) {
            require_once $autoload;

            return;
        }
    }
})();

/**
 * Initialize the object cache.
 */
function wp_cache_init(): void
{
    $adapter = null;

    if (\defined('WPPACK_CACHE_DSN') && WPPACK_CACHE_DSN !== '') {
        try {
            $options = \defined('WPPACK_CACHE_OPTIONS') ? WPPACK_CACHE_OPTIONS : [];
            $prefix = \defined('WPPACK_CACHE_PREFIX') ? WPPACK_CACHE_PREFIX : 'wp:';
            $options['key_prefix'] ??= $prefix;
            $adapter = Adapter::fromDsn(WPPACK_CACHE_DSN, $options);

            if (!$adapter->isAvailable()) {
                $adapter = null;
            }
        } catch (\Throwable) {
            $adapter = null;
        }
    }

    $prefix = \defined('WPPACK_CACHE_PREFIX') ? WPPACK_CACHE_PREFIX : 'wp:';

    $splitStrategies = [];
    if (\defined('WPPACK_CACHE_SPLIT_ALLOPTIONS') && WPPACK_CACHE_SPLIT_ALLOPTIONS) {
        $splitStrategies[] = new AllOptionsSplitStrategy();
        $splitStrategies[] = new NotOptionsSplitStrategy();
        $splitStrategies[] = new SiteOptionsSplitStrategy();
        $splitStrategies[] = new SiteNotOptionsSplitStrategy();
    }

    $maxTtl = \defined('WPPACK_CACHE_MAX_TTL') ? WPPACK_CACHE_MAX_TTL : null;

    $GLOBALS['wp_object_cache'] = new ObjectCache($adapter, $prefix, $splitStrategies, $maxTtl);
}

/**
 * @return ObjectCache
 */
function wp_cache_instance(): ObjectCache
{
    return $GLOBALS['wp_object_cache'];
}

function wp_cache_get(string $key, string $group = '', bool $force = false, bool &$found = false): mixed
{
    return wp_cache_instance()->get($key, $group, $force, $found);
}

/**
 * @param list<string> $keys
 * @return array<string, mixed>
 */
function wp_cache_get_multiple(array $keys, string $group = '', bool $force = false): array
{
    return wp_cache_instance()->getMultiple($keys, $group, $force);
}

function wp_cache_set(string $key, mixed $data, string $group = '', int $expire = 0): bool
{
    return wp_cache_instance()->set($key, $data, $group, $expire);
}

/**
 * @param array<string, mixed> $data
 * @return array<string, bool>
 */
function wp_cache_set_multiple(array $data, string $group = '', int $expire = 0): array
{
    return wp_cache_instance()->setMultiple($data, $group, $expire);
}

function wp_cache_add(string $key, mixed $data, string $group = '', int $expire = 0): bool
{
    return wp_cache_instance()->add($key, $data, $group, $expire);
}

/**
 * @param array<string, mixed> $data
 * @return array<string, bool>
 */
function wp_cache_add_multiple(array $data, string $group = '', int $expire = 0): array
{
    return wp_cache_instance()->addMultiple($data, $group, $expire);
}

function wp_cache_replace(string $key, mixed $data, string $group = '', int $expire = 0): bool
{
    return wp_cache_instance()->replace($key, $data, $group, $expire);
}

function wp_cache_delete(string $key, string $group = ''): bool
{
    return wp_cache_instance()->delete($key, $group);
}

/**
 * @param list<string> $keys
 * @return array<string, bool>
 */
function wp_cache_delete_multiple(array $keys, string $group = ''): array
{
    return wp_cache_instance()->deleteMultiple($keys, $group);
}

function wp_cache_incr(string $key, int $offset = 1, string $group = ''): int|false
{
    return wp_cache_instance()->increment($key, $offset, $group);
}

function wp_cache_decr(string $key, int $offset = 1, string $group = ''): int|false
{
    return wp_cache_instance()->decrement($key, $offset, $group);
}

function wp_cache_flush(): bool
{
    return wp_cache_instance()->flush();
}

function wp_cache_flush_group(string $group): bool
{
    return wp_cache_instance()->flushGroup($group);
}

function wp_cache_flush_runtime(): bool
{
    return wp_cache_instance()->flushRuntime();
}

function wp_cache_supports(string $feature): bool
{
    return wp_cache_instance()->supports($feature);
}

/**
 * @param string|list<string> $groups
 */
function wp_cache_add_global_groups(string|array $groups): void
{
    $groups = (array) $groups;
    wp_cache_instance()->addGlobalGroups($groups);
}

/**
 * @param string|list<string> $groups
 */
function wp_cache_add_non_persistent_groups(string|array $groups): void
{
    $groups = (array) $groups;
    wp_cache_instance()->addNonPersistentGroups($groups);
}

function wp_cache_switch_to_blog(int $blogId): void
{
    wp_cache_instance()->switchToBlog($blogId);
}

function wp_cache_close(): bool
{
    wp_cache_instance()->close();

    return true;
}
