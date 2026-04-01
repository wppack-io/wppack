# Monitoring コンポーネント + CloudWatch Bridge + Plugin 設計

## Context

WpPack にインフラモニタリング機能を追加する。CloudWatch 等からメトリクスを取得し、統一ダッシュボードに表示する。抽象化と接続は Component/Bridge で、何を表示するかは各 Plugin が指示する。

## アーキテクチャ概要

```
RedisCachePlugin ──┐
AmazonMailerPlugin ─┤── MetricSource 登録 ──→ MonitoringRegistry
S3StoragePlugin ───┘                              │
                                                   ▼
                                          MonitoringCollector
                                            │         │
                                    CloudWatch    (Datadog等)
                                    Bridge        将来追加
                                            │
                                    TransientManager (キャッシュ)
                                            │
                                    REST API → React Dashboard
```

### 責務分離

| レイヤー | 責務 |
|---|---|
| **Monitoring コンポーネント** | インターフェース定義、Registry、Collector、REST API、Dashboard Page |
| **CloudWatch Bridge** | `MetricProviderInterface` の AWS CloudWatch 実装 |
| **MonitoringPlugin** | ダッシュボード管理画面の登録 |
| **各 Plugin（Redis 等）** | 自身のメトリクスソースを `MetricSourceProviderInterface` で宣言 |

## コアインターフェース

### `MetricProviderInterface`（Bridge が実装）

2種類のプロバイダを区別する:

- **Query 型**（CloudWatch 等）: 外部にメトリクスが蓄積済み。表示時にオンデマンドで問い合わせる
- **Collect 型**（自前収集が必要な場合）: WP-Cron で定期的にデータを収集・蓄積する

```php
interface MetricProviderInterface
{
    public function getName(): string;
    public function isAvailable(): bool;
    /** @return list<MetricResult> */
    public function query(array $sources, MetricTimeRange $range): array;
}

// 自前収集が必要なプロバイダのみ実装
interface CollectableMetricProviderInterface extends MetricProviderInterface
{
    public function collect(array $sources): void;
    public function getCollectInterval(): int; // seconds
}
```

### `MetricSourceProviderInterface`（Plugin が実装）

```php
interface MetricSourceProviderInterface
{
    /** @return list<MetricSource> */
    public function getSources(): array;
}
```

### 値オブジェクト

```php
// 問い合わせ期間
final readonly class MetricTimeRange {
    public \DateTimeImmutable $start;
    public \DateTimeImmutable $end;
    public int $periodSeconds;  // データポイント間隔
}

// 何を取得するかの宣言
final readonly class MetricSource {
    public string $id;          // 'redis.cache_hits'
    public string $label;       // 'Cache Hits'
    public string $provider;    // 'cloudwatch'
    public string $namespace;   // 'AWS/ElastiCache'
    public string $metricName;  // 'CacheHits'
    public string $unit;        // 'Count'
    public string $stat;        // 'Sum'
    public array $dimensions;   // ['CacheClusterId' => '...']
    public int $periodSeconds;  // 300
    public string $group;       // 'redis'（UI グルーピング）
}

// 取得結果
final readonly class MetricResult {
    public string $sourceId;
    public string $label;
    public string $unit;
    public string $group;
    /** @var list<MetricPoint> */
    public array $datapoints;
    public ?\DateTimeImmutable $fetchedAt;
    public ?string $error;
}

final readonly class MetricPoint {
    public \DateTimeImmutable $timestamp;
    public float $value;
    public string $stat;
}
```

## データフロー

### Query 型（CloudWatch 等 — 外部にデータ蓄積済み）

```
Dashboard → REST API → MonitoringCollector::query()
  → Transient キャッシュヒット? → そのまま返す
  → キャッシュ切れ → CloudWatchMetricProvider::query() → Transient に保存（5分 TTL）
```

WP-Cron 不要。CloudWatch 側がメトリクスを蓄積済みのため、表示時にオンデマンドで取得 + キャッシュ。

### Collect 型（自前収集が必要なプロバイダ）

```
WP-Cron (interval) → MonitoringCollector::runCollectors()
  → CollectableMetricProviderInterface::collect() → データを蓄積

Dashboard → REST API → MonitoringCollector::query()
  → 蓄積済みデータから返す
```

`MonitoringPlugin::boot()` で `CollectableMetricProviderInterface` を実装するプロバイダが存在する場合のみ WP-Cron を登録。

## パッケージ構造

### Monitoring コンポーネント: `src/Component/Monitoring/`

```
src/Component/Monitoring/
├── src/
│   ├── MetricProviderInterface.php
│   ├── MetricSourceProviderInterface.php
│   ├── MetricSource.php
│   ├── MetricResult.php
│   ├── MetricPoint.php
│   ├── MonitoringRegistry.php
│   ├── MonitoringCollector.php          ← query + collect 両フロー統括
│   ├── DependencyInjection/
│   │   ├── MonitoringServiceProvider.php
│   │   └── RegisterMetricProvidersPass.php
│   └── Rest/
│       └── MonitoringController.php
├── tests/
├── composer.json  ← wppack/monitoring
└── README.md
```

### CloudWatch Bridge: `src/Component/Monitoring/Bridge/CloudWatch/`

```
Bridge/CloudWatch/
├── src/
│   ├── CloudWatchMetricProvider.php
│   └── CloudWatchMetricProviderFactory.php
├── tests/
├── composer.json  ← wppack/cloudwatch-monitoring
└── README.md
```

依存: `wppack/monitoring` + `async-aws/cloud-watch`

### MonitoringPlugin: `src/Plugin/MonitoringPlugin/`

```
src/Plugin/MonitoringPlugin/
├── src/
│   ├── MonitoringPlugin.php
│   ├── Admin/
│   │   └── MonitoringDashboardPage.php
│   └── DependencyInjection/
│       └── MonitoringPluginServiceProvider.php
├── js/src/dashboard/  ← React UI
├── composer.json
├── package.json
└── wppack-monitoring.php
```

### Plugin 側の追加（例: RedisCachePlugin）

```
src/Plugin/RedisCachePlugin/src/
└── Monitoring/
    └── RedisCacheMetricSourceProvider.php
```

DI 登録: `->addTag('monitoring.metric_source_provider')`

## マルチクラウド対応

`MetricSource::$provider` 文字列でプロバイダを指定。Bridge がインストールされていなければスキップ。

```php
// CloudWatch の場合
new MetricSource(provider: 'cloudwatch', namespace: 'AWS/ElastiCache', ...)

// 将来 Datadog を追加する場合
new MetricSource(provider: 'datadog', metricName: 'aws.elasticache.cache_hits', ...)
```

新プロバイダ追加は Bridge パッケージ作成のみ。コア変更不要。

## キャッシュ戦略

**Query 型**: `TransientManager` で 5 分間キャッシュ。期限切れ時にオンデマンド再取得。REST `POST /refresh` で手動リフレッシュ可。

**Collect 型**: WP-Cron で定期収集。蓄積先は Transient またはカスタムテーブル（プロバイダが決定）。

共通: `wppack/cache`（Redis drop-in）があれば Transient は自動的に Redis に乗る。

## ダッシュボード

- Admin Page（`tools.php` 配下）に React で描画
- メトリクスカードを `group` 別にグルーピング（Redis、SQS、SES 等）
- スパークラインチャート + 現在値表示
- 手動リフレッシュボタン

## 実装フェーズ

| Phase | 内容 |
|---|---|
| 1 | コアコントラクト（値オブジェクト、インターフェース、Registry、Collector、REST API） |
| 2 | CloudWatch Bridge（`async-aws/cloud-watch` + `GetMetricData` バッチ） |
| 3 | Plugin 統合（RedisCachePlugin に MetricSourceProvider 追加） |
| 4 | React Dashboard UI（メトリクスカード、スパークライン） |
| 5 | (任意) WP Dashboard Widget で概要タイル表示 |

## 検証

1. PHPStan / php-cs-fixer / PHPUnit
2. モック `MetricProvider` で REST API レスポンス確認
3. CloudWatch Bridge の統合テスト（ローカル: LocalStack or AWS sandbox）
4. ブラウザでダッシュボード表示確認
