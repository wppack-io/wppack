# Cloudflare Monitoring ブリッジ

Cloudflare GraphQL Analytics API を使用してメトリクスを取得する `MetricProviderInterface` 実装。

## パッケージ情報

| 項目 | 値 |
|------|-----|
| パッケージ | `wppack/cloudflare-monitoring` |
| ブリッジ名 | `cloudflare` |
| 依存 | `wppack/monitoring` |

外部 Cloudflare SDK 不要。WordPress の `wp_remote_post()` を使用して API を呼び出します。

## 概要

`CloudflareMetricProvider` は Cloudflare の GraphQL Analytics API を呼び出し、Zone アナリティクスと WAF イベントのメトリクスを取得します。設定されたメトリクスに応じて動的に GraphQL クエリを構築し、必要なフィールドのみをリクエストします。

## サポートメトリクス

### Zone Analytics（CDN メトリクス）

| メトリクス | 説明 |
|-----------|------|
| `requests` | HTTP リクエスト合計 |
| `cachedRequests` | キャッシュから配信されたリクエスト |
| `cacheRate` | キャッシュ率（cachedRequests / requests × 100、自動計算） |
| `bandwidth` | データ転送合計 |
| `cachedBandwidth` | キャッシュから配信されたデータ転送 |
| `threats` | ブロックされた脅威 |
| `pageViews` | ページビュー |
| `uniques` | ユニーク訪問者 |
| `status2xx` / `3xx` / `4xx` / `5xx` | HTTP ステータスコード別レスポンス数 |

### WAF イベント

| メトリクス | 説明 |
|-----------|------|
| `wafTotal` | ファイアウォールイベント合計 |
| `wafBlocked` | ブロックされたリクエスト |
| `wafChallenged` | JS チャレンジが発行されたリクエスト |
| `wafManagedChallenge` | マネージドチャレンジが発行されたリクエスト |

## 適応的な時間解像度

| 時間範囲 | 解像度 |
|---------|--------|
| ≤1 時間 | 1 分 |
| ≤6 時間 | 5 分 |
| ≤24 時間 | 15 分 |
| ≤3 日 | 1 時間 |
| >3 日 | 6 時間 |

長時間範囲（>3 日）は 3 日ごとのチャンクに分割して処理し、API タイムアウトを防ぎます。

## API トークン設定

Cloudflare API トークンに以下の権限が必要です:

- **Account** — Account Analytics — Read
- **Zone** — Analytics — Read

1つのトークンを複数のプロバイダ（Zone アナリティクス、WAF 等）で共有できます。

## プロバイダ設定

| フィールド | 型 | 説明 |
|-----------|-----|------|
| `apiToken` | string (sensitive) | Cloudflare API トークン |

## メトリクス定義

| フィールド | 説明 |
|-----------|------|
| `metricName` | メトリクス名（上記サポートメトリクスの ID） |
| `dimensions['ZoneId']` | Cloudflare Zone ID |
| `stat` | 統計タイプ |
