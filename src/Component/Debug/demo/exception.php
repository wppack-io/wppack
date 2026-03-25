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

require_once __DIR__ . '/bootstrap.php';

use WpPack\Component\Debug\DataCollector\AbstractDataCollector;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\FlattenException;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\Panel\CachePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\DatabasePanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\EnvironmentPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\LoggerPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\MemoryPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RequestPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\WordPressPanelRenderer;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

/**
 * Fake collector that injects pre-built data for demo purposes.
 */
final class FakeCollector extends AbstractDataCollector
{
    /**
     * @param array<string, mixed> $fakeData
     */
    public function __construct(
        private readonly string $fakeName,
        private readonly string $fakeLabel,
        private readonly string $fakeIndicatorValue,
        private readonly string $fakeIndicatorColor,
        private readonly array $fakeData,
    ) {}

    public function getName(): string
    {
        return $this->fakeName;
    }

    public function getLabel(): string
    {
        return $this->fakeLabel;
    }

    public function getIndicatorValue(): string
    {
        return $this->fakeIndicatorValue;
    }

    public function getIndicatorColor(): string
    {
        return $this->fakeIndicatorColor;
    }

    public function collect(): void
    {
        $this->data = $this->fakeData;
    }
}

// --- Build a realistic exception chain ---

function simulateDatabaseQuery(string $sql): void
{
    throw new \PDOException(
        'SQLSTATE[HY000] [2002] Connection refused',
        2002,
    );
}

function fetchPostFromDatabase(int $postId): void
{
    try {
        simulateDatabaseQuery("SELECT * FROM wp_posts WHERE ID = {$postId}");
    } catch (\PDOException $e) {
        throw new \RuntimeException(
            "Failed to fetch post #{$postId} from database",
            0,
            $e,
        );
    }
}

function renderSinglePost(int $postId): void
{
    fetchPostFromDatabase($postId);
}

// Capture the exception
$exception = null;
try {
    renderSinglePost(42);
} catch (\Throwable $e) {
    $exception = $e;
}

if ($exception === null) {
    echo 'No exception was thrown.';
    exit(0);
}

// --- Build sample collectors for the debug bar ---

$collectors = [];

$collectors[] = new FakeCollector('request', 'Request', 'GET 500', 'red', [
    'method' => 'GET',
    'status_code' => 500,
    'url' => '/2024/03/hello-world/',
    'route' => 'single',
    'content_type' => 'text/html; charset=UTF-8',
    'headers' => [
        'request' => [
            'Host' => 'example.com',
            'Accept' => 'text/html,application/xhtml+xml',
            'User-Agent' => 'Mozilla/5.0',
        ],
        'response' => [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Powered-By' => 'WpPack',
        ],
    ],
]);

$collectors[] = new FakeCollector('database', 'Database', '12', 'default', [
    'queries' => [
        ['sql' => 'SELECT option_value FROM wp_options WHERE option_name = %s', 'time' => 0.45, 'params' => ['siteurl'], 'caller' => 'get_option()'],
        ['sql' => 'SELECT * FROM wp_posts WHERE ID = %d', 'time' => 1.23, 'params' => [42], 'caller' => 'WP_Query->get_posts()'],
        ['sql' => 'SELECT * FROM wp_postmeta WHERE post_id = %d', 'time' => 0.67, 'params' => [42], 'caller' => 'get_post_meta()'],
    ],
    'total_time' => 2.35,
    'query_count' => 12,
]);

$collectors[] = new FakeCollector('memory', 'Memory', '18.5 MB', 'default', [
    'usage' => memory_get_usage(true),
    'peak' => memory_get_peak_usage(true),
    'limit' => 268435456,
]);

$collectors[] = new FakeCollector('logger', 'Logs', '3', 'red', [
    'total_count' => 3,
    'error_count' => 1,
    'deprecation_count' => 0,
    'logs' => [
        ['level' => 'error', 'message' => 'PDOException: Connection refused', 'context' => ['exception' => 'PDOException'], 'channel' => 'database', 'file' => '/var/www/html/wp-content/plugins/wppack/src/Component/Database/Connection.php', 'line' => 58, 'timestamp' => time()],
        ['level' => 'warning', 'message' => 'Deprecated function called', 'context' => ['_type' => 'deprecation'], 'channel' => 'php', 'file' => '/var/www/html/wp-content/plugins/legacy-plugin/init.php', 'line' => 12, 'timestamp' => time()],
        ['level' => 'info', 'message' => 'Request started', 'context' => [], 'channel' => 'request', 'file' => '', 'line' => 0, 'timestamp' => time()],
    ],
    'level_counts' => ['error' => 1, 'warning' => 1, 'info' => 1],
    'channel_counts' => ['database' => 1, 'php' => 1, 'request' => 1],
]);

$collectors[] = new FakeCollector('cache', 'Cache', '5 / 2', 'default', [
    'hits' => 5,
    'misses' => 2,
    'writes' => 1,
    'deletes' => 0,
    'calls' => [],
    'total_time' => 0.8,
]);

$collectors[] = new FakeCollector('wordpress', 'WordPress', '6.7.2', 'default', [
    'wp_version' => '6.7.2',
    'php_version' => PHP_VERSION,
    'environment_type' => 'development',
    'is_multisite' => false,
    'theme' => 'flavor',
    'is_block_theme' => false,
    'is_child_theme' => false,
    'theme_version' => '1.2.0',
    'active_plugins' => ['wppack/debug' => 'WpPack Debug', 'akismet/akismet.php' => 'Akismet Anti-spam'],
    'constants' => [
        'WP_DEBUG' => true,
        'SAVEQUERIES' => true,
        'SCRIPT_DEBUG' => false,
        'WP_DEBUG_LOG' => false,
        'WP_DEBUG_DISPLAY' => true,
        'WP_CACHE' => false,
    ],
    'extensions' => get_loaded_extensions(),
]);

$collectors[] = new FakeCollector('environment', 'Environment', '', 'default', [
    'php' => [
        'version' => PHP_VERSION,
        'zend_version' => zend_version(),
        'zts' => PHP_ZTS,
        'debug' => PHP_DEBUG,
        'gc_enabled' => gc_enabled(),
    ],
    'sapi' => PHP_SAPI,
    'extensions' => get_loaded_extensions(),
    'ini' => [
        'memory_limit' => ini_get('memory_limit') ?: '',
        'max_execution_time' => ini_get('max_execution_time') ?: '',
        'display_errors' => ini_get('display_errors') ?: '',
    ],
    'opcache' => ['enabled' => false],
    'server' => [
        'software' => $_SERVER['SERVER_SOFTWARE'] ?? '',
        'web_server' => PHP_SAPI === 'cli-server'
            ? ['name' => 'PHP Built-in', 'version' => PHP_VERSION, 'raw' => 'PHP Built-in Server']
            : ['name' => '', 'version' => '', 'raw' => $_SERVER['SERVER_SOFTWARE'] ?? ''],
        'name' => $_SERVER['SERVER_NAME'] ?? '',
        'port' => $_SERVER['SERVER_PORT'] ?? '',
        'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? '',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
    ],
    'runtime' => ['type' => '', 'details' => []],
    'os' => PHP_OS . ' (' . php_uname('r') . ')',
    'architecture' => PHP_INT_SIZE * 8,
    'hostname' => gethostname() ?: '',
]);

// --- Build profile and render toolbar ---

$profile = new Profile(token: 'exception-demo-' . bin2hex(random_bytes(4)));
$profile->setUrl('/2024/03/hello-world/');
$profile->setMethod('GET');
$profile->setStatusCode(500);

foreach ($collectors as $collector) {
    $collector->collect();
    $profile->addCollector($collector);
}

$toolbarRenderer = new ToolbarRenderer($profile);
$toolbarRenderer->addPanelRenderer(new DatabasePanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new MemoryPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new RequestPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new CachePanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new WordPressPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new LoggerPanelRenderer($profile));
$toolbarRenderer->addPanelRenderer(new EnvironmentPanelRenderer($profile));
$toolbarHtml = $toolbarRenderer->render();

// Render the exception page with toolbar
$flatException = FlattenException::createFromThrowable($exception);
$renderer = new ErrorRenderer();
echo $renderer->render($flatException, $toolbarHtml);
