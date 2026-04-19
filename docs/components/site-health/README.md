# SiteHealth コンポーネント

**パッケージ:** `wppack/site-health`
**名前空間:** `WPPack\Component\SiteHealth\`
**レイヤー:** Application

WordPress のサイトヘルスチェック機能（`site_status_tests` フィルター、`debug_information` フィルター）をアトリビュートベースで登録・管理するコンポーネントです。

## インストール

```bash
composer require wppack/site-health
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_filter('site_status_tests', 'my_custom_site_health_tests');
function my_custom_site_health_tests($tests) {
    $tests['direct']['my_custom_test'] = [
        'label' => __('My Custom Test'),
        'test' => 'my_custom_health_check_function',
    ];
    return $tests;
}

function my_custom_health_check_function() {
    $result = [
        'label' => __('My Custom Check'),
        'status' => 'good',
        'badge' => [
            'label' => __('Performance'),
            'color' => 'green',
        ],
        'description' => '<p>' . __('The custom check passed.') . '</p>',
        'actions' => '',
        'test' => 'my_custom_test',
    ];

    if (some_condition_fails()) {
        $result['status'] = 'critical';
        $result['badge']['color'] = 'red';
        $result['description'] = '<p>' . __('The check failed!') . '</p>';
    }

    return $result;
}
```

### After（WPPack）

```php
use WPPack\Component\SiteHealth\HealthCheckInterface;
use WPPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WPPack\Component\SiteHealth\Result;

#[AsHealthCheck(
    id: 'my_custom_test',
    label: 'My Custom Test',
    category: 'performance',
)]
class MyCustomCheck implements HealthCheckInterface
{
    public function run(): Result
    {
        if (some_condition_fails()) {
            return Result::critical(
                label: __('The check failed!', 'my-plugin'),
                description: __('Please fix this issue.', 'my-plugin'),
            );
        }

        return Result::good(
            label: __('My Custom Check', 'my-plugin'),
            description: __('The custom check passed.', 'my-plugin'),
        );
    }
}
```

## クイックスタート

### カスタムヘルスチェックの登録

```php
use WPPack\Component\SiteHealth\HealthCheckInterface;
use WPPack\Component\SiteHealth\Attribute\AsHealthCheck;
use WPPack\Component\SiteHealth\Result;

#[AsHealthCheck(
    id: 'database_optimization',
    label: 'Database Optimization',
    category: 'performance',
)]
class DatabaseOptimizationCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    public function run(): Result
    {
        $overhead = $this->db->getVar(
            "SELECT SUM(DATA_FREE) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
        );

        $overheadMb = round($overhead / 1024 / 1024, 2);

        if ($overheadMb > 100) {
            return Result::critical(
                label: __('Database needs optimization', 'my-plugin'),
                description: sprintf(
                    __('Your database has %sMB of overhead.', 'my-plugin'),
                    $overheadMb
                ),
                actions: sprintf(
                    '<a href="%s">%s</a>',
                    admin_url('tools.php'),
                    __('Optimize Database', 'my-plugin')
                ),
            );
        }

        if ($overheadMb > 50) {
            return Result::recommended(
                label: __('Database could be optimized', 'my-plugin'),
                description: sprintf(
                    __('Your database has %sMB of overhead.', 'my-plugin'),
                    $overheadMb
                ),
            );
        }

        return Result::good(
            label: __('Database is optimized', 'my-plugin'),
            description: __('No significant overhead detected.', 'my-plugin'),
        );
    }
}
```

### 非同期ヘルスチェック

WordPress は非同期テスト（`async` カテゴリ）もサポートしています：

```php
use WPPack\Component\HttpClient\HttpClient;
use WPPack\Component\HttpClient\Exception\ConnectionException;

#[AsHealthCheck(
    id: 'external_api_connectivity',
    label: 'External API Connectivity',
    category: 'security',
    async: true,
)]
class ExternalApiCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly HttpClient $httpClient,
    ) {}

    public function run(): Result
    {
        try {
            $response = $this->httpClient->get('https://api.example.com/health');
        } catch (ConnectionException $e) {
            return Result::recommended(
                label: __('Cannot reach external API', 'my-plugin'),
                description: $e->getMessage(),
            );
        }

        if ($response->failed()) {
            return Result::recommended(
                label: __('External API returned an error', 'my-plugin'),
                description: sprintf(__('Status code: %d', 'my-plugin'), $response->status()),
            );
        }

        return Result::good(
            label: __('External API is reachable', 'my-plugin'),
            description: __('Connection to external API is working.', 'my-plugin'),
        );
    }
}
```

### デバッグ情報の追加

WordPress の「サイトヘルス情報」タブにカスタムセクションを追加します（`debug_information` フィルター）：

```php
use WPPack\Component\SiteHealth\Attribute\AsDebugInfo;
use WPPack\Component\SiteHealth\DebugSectionInterface;

#[AsDebugInfo(
    section: 'my-plugin',
    label: 'My Plugin',
)]
class MyPluginDebugInfo implements DebugSectionInterface
{
    public function getFields(): array
    {
        return [
            'version' => [
                'label' => __('Version', 'my-plugin'),
                'value' => MY_PLUGIN_VERSION,
            ],
            'cache_backend' => [
                'label' => __('Cache Backend', 'my-plugin'),
                'value' => $this->getCacheBackend(),
            ],
            'api_status' => [
                'label' => __('API Status', 'my-plugin'),
                'value' => $this->getApiStatus(),
                'private' => true,
            ],
        ];
    }
}
```

### 依存性注入を使用したチェック

```php
#[AsHealthCheck(
    id: 'cache_status',
    label: 'Object Cache Status',
    category: 'performance',
)]
class CacheStatusCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function run(): Result
    {
        if (!wp_using_ext_object_cache()) {
            return Result::recommended(
                label: __('No persistent object cache', 'my-plugin'),
                description: __('Consider installing a persistent object cache for better performance.', 'my-plugin'),
            );
        }

        return Result::good(
            label: __('Persistent object cache is active', 'my-plugin'),
            description: __('Your site is using a persistent object cache.', 'my-plugin'),
        );
    }
}
```

## Result クラス

`Result` クラスは WordPress のヘルスチェック結果配列をオブジェクト指向でラップします：

```php
// ステータス別のファクトリメソッド
Result::good(label: '...', description: '...');
Result::recommended(label: '...', description: '...');
Result::critical(label: '...', description: '...');

// アクション（修正方法のリンク）を含む
Result::critical(
    label: '...',
    description: '...',
    actions: '<a href="...">Fix this</a>',
);
```

WordPress のステータス値に直接マッピング：

| メソッド | WordPress ステータス | バッジカラー |
|---------|-------------------|------------|
| `Result::good()` | `good` | `green` |
| `Result::recommended()` | `recommended` | `orange` |
| `Result::critical()` | `critical` | `red` |

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — SiteHealth](../hook/site-health.md) を参照してください。

## ヘルスチェック登録

### SiteHealthRegistry（スタンドアロン）

DI コンテナを使わずに、`SiteHealthRegistry` でヘルスチェックとデバッグ情報を直接登録できます。

```php
use WPPack\Component\SiteHealth\SiteHealthRegistry;

add_action('init', function () {
    $registry = new SiteHealthRegistry();
    $registry
        ->add(new DatabaseOptimizationCheck())
        ->add(new CacheStatusCheck())
        ->add(new ExternalApiCheck())
        ->add(new MyPluginDebugInfo())
        ->register();
});
```

`add()` はフルエントインターフェースを提供し、`HealthCheckInterface` と `DebugSectionInterface` の両方を受け付けます。各オブジェクトには対応するアトリビュート（`#[AsHealthCheck]` / `#[AsDebugInfo]`）が必要です。

`register()` は内部で `add_filter('site_status_tests', ...)` と `add_filter('debug_information', ...)` を登録します。冪等なので複数回呼んでも安全です。

### DI コンテナ使用時

```php
add_action('init', function () {
    $container = new WPPack\Container();
    $container->register([
        DatabaseOptimizationCheck::class,
        CacheStatusCheck::class,
        ExternalApiCheck::class,
        MyPluginDebugInfo::class,
    ]);
});
```

## このコンポーネントの使用場面

**最適な用途：**
- プラグインの状態をサイトヘルスに統合する場合
- サーバー環境の要件チェック
- データベースや外部サービスの接続状態監視
- デバッグ情報の提供

**代替を検討すべき場合：**
- サイトヘルスに表示する必要がない内部チェック
- リアルタイムの監視が必要な場合（外部監視ツールを使用）

## 主要クラス

| クラス | 説明 |
|-------|------|
| `HealthCheckInterface` | ヘルスチェック契約 |
| `Result` | テスト結果のラッパー |
| `DebugSectionInterface` | デバッグ情報セクション契約 |
| `SiteHealthRegistry` | スタンドアロン登録（非DI） |
| `Attribute\AsHealthCheck` | ヘルスチェック登録アトリビュート |
| `Attribute\AsDebugInfo` | デバッグ情報登録アトリビュート |

## 依存関係

### 推奨
- **DependencyInjection コンポーネント** — サービス注入用
