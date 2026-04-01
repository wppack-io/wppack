# Monitoring Component — Requirements Definition

## Data Model

```
Monitoring
  └── Provider (1:N)
        ├── Settings (bridge-level config)
        └── Metric (1:N)
```

### Provider

A **Provider** represents a monitored service category and its connection settings.

Examples:
- `redis` — Redis (ElastiCache) via CloudWatch
- `ses` — SES Email via CloudWatch
- `rds` — RDS Database via CloudWatch
- `sqs` — SQS Queue via CloudWatch (future)

Each provider has:

| Field | Type | Description |
|---|---|---|
| `id` | string | Unique identifier (e.g., `redis`, `ses`, `rds`) |
| `label` | string | Display name (e.g., "Redis (ElastiCache)") |
| `bridge` | string | Which bridge fetches data (e.g., `cloudwatch`) |
| `locked` | bool | Plugin-registered = true, user-created = false |

### Provider Settings (bridge-level)

Settings specific to the bridge. For AWS CloudWatch:

| Field | Type | Required | Description |
|---|---|---|---|
| `region` | string | Optional | AWS region (falls back to `AWS_DEFAULT_REGION` / `us-east-1`) |
| `access_key_id` | string | Optional | IAM access key (falls back to instance role / env) |
| `secret_access_key` | string | Optional | IAM secret key |

These are per-provider, not global — different providers may use different regions or credentials.

### Metric

A **Metric** belongs to a provider and defines what data to fetch.

| Field | Type | Default | Description |
|---|---|---|---|
| `id` | string | | Unique identifier (e.g., `redis.cache_hits`) |
| `label` | string | | Display name (e.g., "Cache Hits") |
| `namespace` | string | | CloudWatch namespace (e.g., `AWS/ElastiCache`) |
| `metricName` | string | | CloudWatch metric name (e.g., `CacheHits`) |
| `unit` | string | `Count` | Unit: Count, Percent, Bytes, Seconds, etc. |
| `stat` | string | `Average` | Statistic: Average, Sum, Maximum, Minimum, SampleCount |
| `dimensions` | map | `{}` | Key-value pairs (e.g., `CacheClusterId: xxx`) |
| `periodSeconds` | int | `300` | Data point interval |
| `locked` | bool | `false` | Plugin-registered = true, user-created = false |

## Registration Sources

### 1. Plugin-registered (locked)

Plugins register providers and metrics via `MetricSourceProviderInterface` in their ServiceProvider. These are read-only in the UI.

| Plugin | Provider | Metrics |
|---|---|---|
| RedisCachePlugin | `redis` (ElastiCache) | CacheHits, CacheMisses, CurrConnections, EngineCPU, MemoryUsage |
| AmazonMailerPlugin | `ses` (SES) | Send, Bounce, Complaint, Delivery, Reject |
| *(user-created)* | `rds` | User-defined |

### 2. User-created (unlocked)

Users create providers and metrics from the Monitoring settings page. These are stored in `wp_options` and can be edited/deleted.

Example: User creates an "RDS" provider with CloudWatch credentials, then adds metrics like `DatabaseConnections`, `CPUUtilization`, etc.

## Metric Catalog per Service

### Redis (ElastiCache) — Namespace: `AWS/ElastiCache`

| Metric | Stat | Unit | Description |
|---|---|---|---|
| `CacheHits` | Sum | Count | Number of successful cache lookups |
| `CacheMisses` | Sum | Count | Number of unsuccessful cache lookups |
| `CurrConnections` | Average | Count | Number of client connections |
| `EngineCPUUtilization` | Average | Percent | Engine thread CPU utilization |
| `DatabaseMemoryUsagePercentage` | Average | Percent | Memory usage percentage |
| `NetworkBytesIn` | Sum | Bytes | Bytes read from network |
| `NetworkBytesOut` | Sum | Bytes | Bytes written to network |
| `ReplicationLag` | Average | Seconds | Replication lag (read replicas) |
| `Evictions` | Sum | Count | Keys evicted due to memory limit |
| `CurrItems` | Average | Count | Number of items in cache |

Dimensions: `CacheClusterId`

### SES — Namespace: `AWS/SES`

| Metric | Stat | Unit | Description |
|---|---|---|---|
| `Send` | Sum | Count | Emails sent |
| `Delivery` | Sum | Count | Emails successfully delivered |
| `Bounce` | Sum | Count | Hard and soft bounces |
| `Complaint` | Sum | Count | Spam complaints |
| `Reject` | Sum | Count | Rejected sends |
| `Open` | Sum | Count | Emails opened (if tracking enabled) |
| `Click` | Sum | Count | Links clicked (if tracking enabled) |
| `RenderingFailure` | Sum | Count | Template rendering failures |

Dimensions: (none — account-level metrics)

### RDS — Namespace: `AWS/RDS`

| Metric | Stat | Unit | Description |
|---|---|---|---|
| `CPUUtilization` | Average | Percent | CPU usage |
| `DatabaseConnections` | Average | Count | Active connections |
| `FreeableMemory` | Average | Bytes | Available RAM |
| `FreeStorageSpace` | Average | Bytes | Available storage |
| `ReadIOPS` | Average | Count/Second | Read I/O operations |
| `WriteIOPS` | Average | Count/Second | Write I/O operations |
| `ReadLatency` | Average | Seconds | Read latency |
| `WriteLatency` | Average | Seconds | Write latency |
| `NetworkReceiveThroughput` | Average | Bytes/Second | Network in |
| `NetworkTransmitThroughput` | Average | Bytes/Second | Network out |
| `ReplicaLag` | Average | Seconds | Read replica lag |

Dimensions: `DBInstanceIdentifier`

## UI Requirements

### Monitoring Dashboard

AWS CloudWatch ダッシュボード風の情報集約型レイアウト。プロバイダ単位でセクション分けし、各メトリクスに説明文を付ける。

**プロバイダセクション（例: Redis）:**
```
┌─────────────────────────────────────────────────────┐
│ Redis (ElastiCache)                     [Refresh ↻] │
│ ap-northeast-1 · CacheClusterId: prod-redis-001    │
├─────────────────────────────────────────────────────┤
│ ┌──────────────────┐ ┌──────────────────┐           │
│ │ Cache Hits       │ │ Cache Misses     │           │
│ │ 1.2M             │ │ 3.4K             │           │
│ │ ~~~~~~~~ (spark) │ │ ~~~~~~~~ (spark) │           │
│ │ Sum over 5 min   │ │ Sum over 5 min   │           │
│ └──────────────────┘ └──────────────────┘           │
│ ┌──────────────────┐ ┌──────────────────┐           │
│ │ CPU Utilization  │ │ Memory Usage     │           │
│ │ 12.3%            │ │ 45.6%            │           │
│ │ ~~~~~~~~ (spark) │ │ ~~~~~~~~ (spark) │           │
│ │ Average, Engine  │ │ Average, DB mem  │           │
│ └──────────────────┘ └──────────────────┘           │
└─────────────────────────────────────────────────────┘
```

**メトリクスカード構成:**
- メトリクス名（ラベル）
- 現在値（最新データポイント）。フォーマット: %, K, M, bytes 等
- スパークライン（過去 N 時間のトレンド）
- 説明文（stat + unit + context）
- エラー時: エラーメッセージ表示

**ダッシュボード全体:**
- プロバイダセクションがカード形式で縦に並ぶ
- 各セクションヘッダー: プロバイダ名、リージョン、主要ディメンション
- 期間セレクター（1h / 3h / 6h / 12h / 24h）
- 全体リフレッシュボタン
- プロバイダが0件の場合: 設定ページへの誘導

### Monitoring Settings (tab within the same admin page)

**技術スタック:**
- `@wordpress/dataviews` — `DataViews` (Table layout) でプロバイダ一覧表示
- `@wordpress/dataviews` — `DataForm` でプロバイダ/メトリクス編集
- TabPanel で Dashboard / Settings を切替

**Provider 一覧 (DataViews Table):**
- Columns: Label, Bridge, Region, Status (🔒 Plugin / Custom), Metrics count
- Plugin-registered: lock icon, click → read-only detail
- User-created: click → editable detail, delete action available
- "Add Provider" button in header

**Provider 詳細 (DataForm, row click で表示):**
- General: Label, Bridge type (select: CloudWatch / future)
- AWS Settings (collapsible card): Region, Access Key ID, Secret Access Key
- Metrics: nested DataViews table with Add/Edit/Delete

**Metric 編集 (DataForm):**
- Label, Description (textarea), Namespace, Metric Name
- Stat (select: Average/Sum/Max/Min), Unit (select: Count/Percent/Bytes/Seconds)
- Period (integer), Dimensions (key-value pairs)

**Locked (Plugin 由来) の制約:**
- DataViews actions に `isEligible: item => !item.locked`
- DataForm fields に `readOnly: true`
- 削除ボタン非表示

## Configuration Precedence

For plugin-registered providers, settings resolve in order:
1. PHP constants (e.g., `WPPACK_MONITORING_ELASTICACHE_REGION`)
2. Environment variables
3. Provider settings from Monitoring settings page (wp_options)
4. Defaults

## Storage

- Provider settings: `wp_options` key `wppack_monitoring_providers`
- User-created metrics: embedded in provider data
- Plugin-registered providers/metrics: in-memory only (via `MetricSourceProviderInterface`)

## Future Extensibility

- Additional bridges: Datadog, Azure Monitor, GCP Cloud Monitoring
- Additional plugins registering metrics: SqsMessengerPlugin, EventBridgeSchedulerPlugin
- Per-metric alerting thresholds (Phase 2)