# Debug コンポーネント

Debug コンポーネントは、高度なプロファイリング、ロギング、パフォーマンスモニタリング機能を備えた、WordPress アプリケーション向けの Symfony スタイルのデバッグインターフェースを提供します。

## このコンポーネントの機能

Debug コンポーネントは以下の機能で WordPress のデバッグを変革します：

- **モダンな Web デバッグツールバー** - Symfony スタイルのインターフェース
- **パフォーマンスプロファイリングとモニタリング** - メソッドレベルのタイミングとメモリトラッキング
- **データベースクエリ分析と最適化** - 重複検出とスロークエリ警告
- **メモリ使用量トラッキング** - スナップショットとリーク検出
- **リクエスト/レスポンスインスペクション** - 詳細な HTTP 分析
- **カスタムコレクターシステム** - 拡張可能なデバッグ機能
- **エラーハンドリングとスタックトレース** - 包括的なエラートラッキング
- **テンプレートデバッグ** - レンダリング分析と最適化提案
- **キャッシュデバッグ** - オペレーショントラッキングとヒット率分析

## インストール

```bash
composer require wppack/debug
```

## 従来の WordPress vs WpPack

### Before（従来の WordPress）

```php
// 従来の WordPress - 限定的な情報での基本的なデバッグ
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
define('SAVEQUERIES', true);

error_log('Debug message');

global $wpdb;
$wpdb->show_errors();
print_r($wpdb->queries);

echo 'Memory: ' . memory_get_usage(true);
echo 'Peak: ' . memory_get_peak_usage(true);

$start = microtime(true);
// Some code
$end = microtime(true);
echo 'Time: ' . ($end - $start);
```

### After（WpPack）

```php
use WpPack\Component\Debug\AbstractDebugCollector;
use WpPack\Component\Debug\Attribute\DebugCollector;
use WpPack\Component\Debug\Attribute\Profile;
use WpPack\Component\Debug\DebugBar;
use WpPack\Component\Debug\Profiler;

#[DebugCollector('database')]
class DatabaseCollector extends AbstractDebugCollector
{
    private array $queries = [];
    private float $totalTime = 0;

    public function __construct(
        private DatabaseInterface $database,
        private LoggerInterface $logger
    ) {}

    #[Profile('database.query')]
    public function collectQuery(string $sql, array $bindings, float $time): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            'duplicates' => $this->findDuplicates($sql)
        ];

        $this->totalTime += $time;

        if ($time > 100) {
            $this->logger->warning('Slow query detected', [
                'sql' => $sql,
                'time' => $time
            ]);
        }
    }

    public function getData(): array
    {
        return [
            'queries' => $this->queries,
            'total_time' => $this->totalTime,
            'count' => count($this->queries),
            'duplicates' => $this->getDuplicateQueries(),
            'slow_queries' => $this->getSlowQueries(),
            'suggestions' => $this->getOptimizationSuggestions()
        ];
    }
}

// アトリビュートによるパフォーマンスプロファイリング
class OrderService
{
    #[Profile('order.create')]
    public function createOrder(array $data): Order
    {
        return $this->profiler->profile('order.create', function() use ($data) {
            $user = $this->profiler->profile('order.user_lookup', function() use ($data) {
                return $this->db->find('users', $data['user_id']);
            });

            $order = $this->profiler->profile('order.save', function() use ($data) {
                return $this->db->save('orders', $data);
            });

            return $order;
        });
    }
}
```

## コア機能

### デバッグ設定

```php
use WpPack\Component\DependencyInjection\Attribute\Env;

final readonly class DebugConfig
{
    public function __construct(
        #[Env('WPPACK_DEBUG_ENABLED')]
        public bool $enabled = false,

        #[Env('WPPACK_DEBUG_BAR_ENABLED')]
        public bool $showDebugBar = false,

        /** @var list<string> */
        public array $collectors = ['database', 'request', 'memory', 'templates'],

        /** @var list<string> */
        public array $ipWhitelist = ['127.0.0.1', '::1'],

        /** @var list<string> */
        public array $userRoleWhitelist = ['administrator'],
    ) {}

    public function isEnabled(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (wp_get_environment_type() === 'production' && !$this->isIpWhitelisted()) {
            return false;
        }

        return $this->hasPermission();
    }

    public function shouldShowDebugBar(): bool
    {
        return $this->isEnabled() && $this->showDebugBar && !wp_doing_ajax();
    }
}
```

### デバッグバーの初期化

```php
class DebugService
{
    public function __construct(
        private DebugBar $debugBar,
        private DebugConfig $config
    ) {}

    #[Action('init')]
    public function onInit(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->debugBar->addCollector(new DatabaseCollector());
        $this->debugBar->addCollector(new RequestCollector());
        $this->debugBar->addCollector(new MemoryCollector());
        $this->debugBar->addCollector(new TemplateCollector());
    }

    #[Action('wp_footer')]
    public function renderDebugBar(): void
    {
        if (!$this->config->shouldShowDebugBar()) {
            return;
        }

        echo $this->debugBar->render();
    }
}
```

### データベースプロファイリング

```php
#[DebugCollector('database')]
class DatabaseCollector extends AbstractDebugCollector
{
    private array $queries = [];
    private float $totalTime = 0;

    public function collectQuery(string $sql, array $bindings, float $time): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time' => $time,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
            'duplicates' => $this->findDuplicates($sql),
            'formatted_sql' => $this->formatSql($sql),
            'explain' => $this->explainQuery($sql, $bindings)
        ];

        $this->totalTime += $time;

        if ($time > 100) {
            $this->logger->warning('Slow query detected', [
                'sql' => $sql,
                'time' => $time . 'ms'
            ]);
        }
    }

    public function getData(): array
    {
        return [
            'queries' => $this->queries,
            'total_time' => $this->totalTime,
            'count' => count($this->queries),
            'duplicates' => $this->getDuplicateQueries(),
            'slow_queries' => $this->getSlowQueries(),
            'average_time' => count($this->queries) > 0
                ? $this->totalTime / count($this->queries) : 0,
            'suggestions' => $this->getOptimizationSuggestions()
        ];
    }

    private function getOptimizationSuggestions(): array
    {
        $suggestions = [];

        if (count($this->getDuplicateQueries()) > 0) {
            $suggestions[] = 'Consider caching duplicate queries to improve performance';
        }

        if (count($this->getSlowQueries()) > 0) {
            $suggestions[] = 'Optimize slow queries with proper indexes';
        }

        if ($this->totalTime > 1000) {
            $suggestions[] = 'Total query time is high - consider overall database optimization';
        }

        if (count($this->queries) > 50) {
            $suggestions[] = 'High number of queries detected - consider query consolidation';
        }

        return $suggestions;
    }
}
```

### メモリ使用量分析

```php
#[DebugCollector('memory')]
class MemoryCollector extends AbstractDebugCollector
{
    private array $snapshots = [];
    private int $peakMemory = 0;

    public function takeSnapshot(string $label): void
    {
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        $this->snapshots[] = [
            'label' => $label,
            'memory' => $current,
            'peak' => $peak,
            'time' => microtime(true),
            'formatted' => $this->formatBytes($current),
            'peak_formatted' => $this->formatBytes($peak)
        ];

        if (count($this->snapshots) > 1) {
            $growth = $current - $this->snapshots[0]['memory'];
            if ($growth > 50 * 1024 * 1024) {
                $this->logger->warning('Potential memory leak detected', [
                    'growth' => $this->formatBytes($growth)
                ]);
            }
        }
    }

    public function getData(): array
    {
        $this->takeSnapshot('final');

        return [
            'snapshots' => $this->snapshots,
            'peak_memory' => $this->peakMemory,
            'peak_formatted' => $this->formatBytes($this->peakMemory),
            'memory_limit' => $this->parseMemoryLimit(),
            'usage_percentage' => round(($this->peakMemory / $this->parseMemoryLimit()) * 100, 2),
            'memory_growth' => $this->calculateMemoryGrowth(),
            'suggestions' => $this->getMemorySuggestions()
        ];
    }
}
```

### 自動メモリスナップショット

```php
class MemoryDebugService
{
    public function __construct(
        private MemoryCollector $collector
    ) {}

    #[Action('wp_loaded')]
    public function wpLoadedSnapshot(): void
    {
        $this->collector->takeSnapshot('wp_loaded');
    }

    #[Action('template_redirect')]
    public function templateRedirectSnapshot(): void
    {
        $this->collector->takeSnapshot('template_redirect');
    }

    #[Action('wp_footer')]
    public function wpFooterSnapshot(): void
    {
        $this->collector->takeSnapshot('wp_footer');
    }

    #[Action('shutdown')]
    public function shutdownSnapshot(): void
    {
        $this->collector->takeSnapshot('shutdown');
    }
}
```

### アトリビュートによるパフォーマンスプロファイリング

```php
use WpPack\Component\Debug\Attribute\Profile;
use WpPack\Component\Debug\Profiler;

class ProductService
{
    public function __construct(
        private DatabaseInterface $database,
        private CacheInterface $cache,
        private Profiler $profiler
    ) {}

    #[Profile('product.fetch')]
    public function getProduct(int $id): ?Product
    {
        return $this->profiler->profile('product.cache_check', function() use ($id) {
            $cached = $this->cache->get("product:{$id}");
            if ($cached) {
                return $cached;
            }

            return $this->profiler->profile('product.database_fetch', function() use ($id) {
                $product = $this->database->find('products', $id);

                if ($product) {
                    $this->cache->set("product:{$id}", $product, 3600);
                }

                return $product;
            });
        });
    }

    #[Profile('product.search')]
    public function searchProducts(string $query, array $filters = []): array
    {
        return $this->profiler->profile('product.search_execution', function() use ($query, $filters) {
            $searchQuery = $this->profiler->profile('product.build_query', function() use ($query, $filters) {
                return $this->buildSearchQuery($query, $filters);
            });

            $results = $this->profiler->profile('product.execute_search', function() use ($searchQuery) {
                return $this->database->query($searchQuery);
            });

            return $this->profiler->profile('product.process_results', function() use ($results) {
                return array_map([$this, 'processSearchResult'], $results);
            });
        });
    }
}
```

### テンプレートパフォーマンスデバッグ

```php
#[DebugCollector('templates')]
class TemplateCollector extends AbstractDebugCollector
{
    private array $templates = [];

    #[Action('template_include', priority: 999)]
    public function trackTemplate(string $template): string
    {
        $this->templates[] = [
            'file' => $template,
            'type' => $this->getTemplateType(),
            'hierarchy' => $this->getTemplateHierarchy(),
            'time' => microtime(true),
            'memory_before' => memory_get_usage(true)
        ];

        return $template;
    }
}
```

### キャッシュ操作モニタリング

```php
#[DebugCollector('cache')]
class CacheCollector extends AbstractDebugCollector
{
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];

    public function recordOperation(string $operation, string $key, $value = null, bool $hit = null): void
    {
        switch ($operation) {
            case 'get':
                $this->stats[$hit ? 'hits' : 'misses']++;
                break;
            case 'set':
                $this->stats['sets']++;
                break;
            case 'delete':
                $this->stats['deletes']++;
                break;
        }
    }

    public function getCacheSuggestions(): array
    {
        $suggestions = [];

        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? ($this->stats['hits'] / $total) * 100 : 0;

        if ($hitRate < 70) {
            $suggestions[] = 'Cache hit rate is low - consider caching more data or longer TTL';
        }

        return $suggestions;
    }
}
```

### カスタムデバッグコレクター

```php
#[DebugCollector('wordpress')]
class WordPressCollector extends AbstractDebugCollector
{
    public function getData(): array
    {
        return [
            'hooks' => $this->collectHookData(),
            'plugins' => get_option('active_plugins'),
            'theme' => get_template(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'environment' => wp_get_environment_type(),
            'performance_suggestions' => $this->getPerformanceSuggestions()
        ];
    }
}
```

## テスト

```php
use WpPack\Component\Debug\Testing\DebugTestCase;

class DebugSetupTest extends DebugTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableDebug();
    }

    public function testDebugBarRenders(): void
    {
        $debugBar = $this->getDebugBar();
        $debugBar->addCollector(new DatabaseCollector($this->createMock(LoggerInterface::class)));

        $output = $debugBar->render();

        $this->assertStringContains('wppack-debug-bar', $output);
        $this->assertStringContains('Database', $output);
    }

    public function testDatabaseCollector(): void
    {
        $collector = new DatabaseCollector($this->createMock(LoggerInterface::class));

        $collector->collectQuery('SELECT * FROM posts', [], 50.0);
        $collector->collectQuery('SELECT * FROM posts', [], 25.0);

        $data = $collector->getData();

        $this->assertEquals(2, $data['count']);
        $this->assertEquals(75.0, $data['total_time']);
        $this->assertCount(1, $data['duplicates']);
    }

    public function testProfilerIntegration(): void
    {
        $profiler = $this->getProfiler();

        $result = $profiler->profile('test.operation', function() {
            usleep(1000);
            return 'test result';
        });

        $this->assertEquals('test result', $result);

        $profile = $profiler->getProfile('test.operation');
        $this->assertGreaterThan(0, $profile['duration']);
    }
}
```

## クイックリファレンス

### 基本的なデバッグセットアップ

```php
class DebugService
{
    #[Action('init')]
    public function onInit(): void
    {
        if (!WP_DEBUG) return;

        $this->debugBar->addCollector(new DatabaseCollector());
        $this->debugBar->addCollector(new MemoryCollector());
    }

    #[Action('wp_footer')]
    public function renderDebugBar(): void
    {
        echo $this->debugBar->render();
    }
}
```

### プロファイリングメソッド

```php
#[Profile('operation.name')]
public function myMethod(): string
{
    return $this->profiler->profile('nested.operation', function() {
        return 'result';
    });
}
```

### メモリスナップショット

```php
$collector->takeSnapshot('custom_checkpoint');
```

### 環境変数

```env
WPPACK_DEBUG_ENABLED=true
WPPACK_DEBUG_BAR_ENABLED=true
```

## このコンポーネントの使用場面

**最適な用途：**
- 高度なデバッグ機能が必要な WordPress アプリケーション
- 包括的なプロファイリングツールが必要な開発チーム
- パフォーマンス最適化が必要なサイト
- カスタム機能を持つ複雑な WordPress アプリケーション
- Symfony スタイルのデバッグ体験を求めるチーム

**代替を検討すべき場合：**
- 基本的なデバッグで十分なシンプルな WordPress サイト
- パフォーマンス要件のない本番サイト
- WordPress デフォルトのデバッグのみを使用するプロジェクト

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress アクション/フィルター登録用
- **DependencyInjection コンポーネント** - サービスコンテナとオートワイヤリング用

### 推奨
- **DependencyInjection コンポーネント** - デバッグ設定管理用（`#[Env]` アトリビュート）
- **Database コンポーネント** - データベースクエリプロファイリングと分析用
- **Cache コンポーネント** - キャッシュ操作デバッグ用
