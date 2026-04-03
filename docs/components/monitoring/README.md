# Monitoring コンポーネント

プロバイダ非依存のインフラモニタリング基盤。メトリクス収集・キャッシュ・REST API を提供し、プラガブルなブリッジを通じて外部サービスからメトリクスを取得します。

## パッケージ構成

| パッケージ | 説明 |
|-----------|------|
| `wppack/monitoring` | コアコンポーネント（Registry, Collector, Store, REST API） |
| `wppack/cloudwatch-monitoring` | AWS CloudWatch ブリッジ |
| `wppack/cloudflare-monitoring` | Cloudflare Analytics ブリッジ |

## ドキュメント

- [アーキテクチャ設計](./architecture.md) — コアインターフェース、データフロー、パッケージ構造
- [要件定義](./requirements.md) — データモデル、メトリクスカタログ、UI 要件
- [CloudWatch ブリッジ](./cloudwatch-monitoring.md) — AWS CloudWatch GetMetricData 連携
- [Cloudflare ブリッジ](./cloudflare-monitoring.md) — Cloudflare GraphQL Analytics 連携

## アーキテクチャ概要

```
MonitoringProviderInterface    ← プロバイダ検出（Auto-Discovery, wp_options）
    ↓
MonitoringRegistry             ← 全プロバイダ収集
    ↓
MonitoringCollector            ← ブリッジ経由でメトリクス取得・キャッシュ
    ↓
MetricProviderInterface        ← ブリッジ契約
    ├── CloudWatchMetricProvider  (wppack/cloudwatch-monitoring)
    ├── CloudflareMetricProvider  (wppack/cloudflare-monitoring)
    └── MockMetricProvider        (組み込み、ローカル開発用)
```

## コアインターフェース

### MetricProviderInterface

外部サービスからメトリクスを取得するブリッジ契約。

```php
interface MetricProviderInterface
{
    public function getName(): string;
    public function isAvailable(): bool;
    public function query(MonitoringProvider $provider, MetricTimeRange $range): array;
}
```

### MonitoringProviderInterface

モニタリングプロバイダの設定を検出・提供するインターフェース。

```php
interface MonitoringProviderInterface
{
    /** @return list<MonitoringProvider> */
    public function getProviders(): array;
}
```

## データモデル

| クラス | 説明 |
|-------|------|
| `MonitoringProvider` | プロバイダ定義（id, label, bridge, settings, metrics） |
| `ProviderSettings` | 接続設定（region, credentials） |
| `MetricDefinition` | メトリクス仕様（namespace, name, dimensions, period） |
| `MetricTimeRange` | クエリ時間範囲（start, end, periodSeconds） |
| `MetricResult` | クエリ結果（datapoints, error） |
| `MetricPoint` | 単一データポイント（timestamp, value, stat） |
