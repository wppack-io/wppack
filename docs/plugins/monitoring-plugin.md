# MonitoringPlugin

インフラストラクチャモニタリングダッシュボードを提供する WordPress プラグイン。AWS CloudWatch および Cloudflare Analytics からリアルタイムでメトリクスを収集・表示します。

## 概要

- AWS CloudWatch / Cloudflare Analytics のメトリクスをリアルタイムで表示するダッシュボード
- WPPack プラグインの設定から AWS サービスを自動検出（Auto-Discovery）
- DataViews / DataForm ベースの設定 UI
- テンプレートシステムによるプロバイダの簡単追加
- プロバイダごとのメトリクスカード（スパークライングラフ付き）
- 設定可能な時間範囲（1h, 3h, 6h, 12h, 1d, 3d, 7d）

## アーキテクチャ

### パッケージ構成

```
MonitoringPlugin (wppack/monitoring-plugin)
├── Dashboard UI (React + WordPress Components)
├── Auto-Discovery (DB, ElastiCache, SES, S3)
├── Metric Templates (RDS, Lambda, Cloudflare, etc.)
└── DI Service Registration
    ↓
Monitoring (wppack/monitoring)
├── MonitoringRegistry (provider collection)
├── MonitoringCollector (query + cache)
├── MonitoringStore (wp_options persistence)
└── REST API Controllers
    ↓
Bridges
├── CloudWatchMetricProvider (wppack/cloudwatch-monitoring)
│   └── async-aws/cloud-watch
└── CloudflareMetricProvider (wppack/cloudflare-monitoring)
    └── Cloudflare GraphQL API
```

### レイヤー構成

```
src/Plugin/MonitoringPlugin/
├── wppack-monitoring.php          # プラグインエントリポイント
├── src/
│   ├── MonitoringPlugin.php       # DI コンテナ設定
│   ├── Admin/
│   │   └── MonitoringDashboardPage.php  # 管理ページ登録
│   ├── DependencyInjection/
│   │   └── MonitoringPluginServiceProvider.php  # サービス登録
│   ├── Discovery/                 # Auto-Discovery
│   │   ├── DatabaseDiscovery.php  # RDS/Aurora 検出
│   │   ├── ElastiCacheDiscovery.php  # Redis 検出
│   │   ├── S3Discovery.php       # S3 検出
│   │   └── SesDiscovery.php      # SES 検出
│   ├── Rest/
│   │   └── SyncTemplatesController.php  # テンプレート同期
│   └── Template/
│       ├── MetricTemplateRegistry.php  # テンプレート定義
│       └── MetricTemplate.php     # テンプレートデータクラス
└── js/
    └── src/dashboard/             # React フロントエンド
        ├── App.js                 # タブレイアウト
        └── pages/
            ├── DashboardPage.js   # メトリクス表示
            └── SettingsPage.js    # プロバイダ管理
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| `wppack/monitoring` | メトリクス収集・キャッシュ・REST API |
| `wppack/cloudwatch-monitoring` | AWS CloudWatch メトリクス取得 |
| `wppack/cloudflare-monitoring` | Cloudflare アナリティクス取得 |
| `wppack/kernel` | プラグインブートストラップ |
| `wppack/dependency-injection` | DI コンテナ |
| `wppack/hook` | WordPress フック管理 |

## Auto-Discovery

既存の WPPack プラグイン設定から AWS サービスを自動検出します。検出されたプロバイダは「プラグイン」ソースとして locked 状態で登録され、編集・削除できません。

| Discovery | 検出元 | 検出ロジック |
|-----------|--------|-------------|
| `DatabaseDiscovery` | `DB_HOST` | Aurora クラスターエンドポイント（`.cluster-`）を検出し RDS/Aurora を判別 |
| `ElastiCacheDiscovery` | `CACHE_DSN` | `redis://` / `rediss://` スキームから ElastiCache を検出 |
| `SesDiscovery` | `MAILER_DSN` | `ses://` スキームから SES を検出 |
| `S3Discovery` | Storage 設定 | S3Storage 設定からバケット名・リージョンを検出 |

## メトリクステンプレート

テンプレートを使ってプロバイダを簡単に追加できます。各テンプレートには推奨メトリクスが事前定義されています。

### AWS CloudWatch

| テンプレート | ディメンション | メトリクス数 |
|-------------|---------------|-------------|
| RDS / Aurora | DBInstanceIdentifier | 6 |
| Aurora Cluster | DBClusterIdentifier | 6 |
| ElastiCache (Redis) | CacheClusterId | 5 |
| CloudFront | DistributionId | 4 |
| Lambda | FunctionName | 4 |
| SQS | QueueName | 3 |
| S3 | BucketName | 2 |
| EC2 | InstanceId | 4 |

### Cloudflare

| テンプレート | ディメンション | メトリクス数 |
|-------------|---------------|-------------|
| Cloudflare Zone | ZoneId | 12 |
| Cloudflare WAF | ZoneId | 4 |

## REST API

すべてのエンドポイントは `wppack/v1/monitoring` 名前空間で、`manage_options` 権限が必要です。

| メソッド | パス | 説明 |
|---------|------|------|
| GET | `/metrics` | メトリクスデータ取得（period パラメータ: 1–168 時間） |
| POST | `/refresh` | メトリクスの強制更新 |
| GET | `/providers` | プロバイダ一覧取得 |
| POST | `/providers` | プロバイダ追加 |
| PUT | `/providers` | プロバイダ更新（locked 不可） |
| DELETE | `/providers` | プロバイダ削除（locked 不可） |
| POST | `/sync-templates` | テンプレートからメトリクス定義を同期 |

## 設定

### IAM ポリシー（AWS CloudWatch）

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "WPPackMonitoring",
      "Effect": "Allow",
      "Action": "cloudwatch:GetMetricData",
      "Resource": "*"
    }
  ]
}
```

`cloudwatch:GetMetricData` はリソースレベルの制限をサポートしないため、Resource は `"*"` にする必要があります。

### Cloudflare API トークン

1. Cloudflare ダッシュボードで **My Profile → API Tokens** に移動
2. **Create Custom Token** を選択
3. 権限を設定:
   - Account — Account Analytics — Read
   - Zone — Analytics — Read
4. Account Resources / Zone Resources を選択してトークンを作成

1つの API トークンを複数のプロバイダ（Zone アナリティクス、WAF 等）で共有できます。

## Settings ページ

WordPress 管理画面のトップレベルメニュー「Monitoring」（position 90）から設定ページにアクセスできます。

- **ダッシュボード** タブ: メトリクスカードのリアルタイム表示
- **設定** タブ: DataViews テーブルによるプロバイダ管理
  - テンプレートからプロバイダを追加（AWS CloudWatch / Cloudflare でグループ化）
  - DataForm による編集（認証情報、ディメンション、メトリクス）
  - プラグイン管理プロバイダは読み取り専用表示
