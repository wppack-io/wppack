# ターゲットインフラストラクチャ

## マルチクラウド対応（AWS / GCP / Azure）

### 設計方針

- コアインターフェース（Abstraction Layer）はクラウド非依存
- プロバイダ固有の実装は Bridge パッケージとして分離
- AWS ファーストで開発し、GCP・Azure に順次拡大
- Symfony の Transport / Adapter パターンに従う

### Bridge パッケージ命名規約

- `wppack/{provider}-{component}`（例: amazon-mailer, azure-mailer）
- クラウド非依存のバックエンドは機能名のみ（例: redis-cache, memcached-cache）

### 対応状況

| 機能 | コアインターフェース | AWS | GCP | Azure | その他 |
|------|---------------------|-----|-----|-------|--------|
| メール送信 | Mailer | AmazonMailer (SES) | — | AzureMailer (ACS) | SendGridMailer |
| キャッシュ | Cache | DynamoDbCache, ElastiCacheAuth | — | — | RedisCache, MemcachedCache, ApcuCache |
| メッセージング | Messenger | SQS/Lambda | — | — | — |
| スケジューラー | Scheduler | EventBridge | — | — | — |
| ストレージ | Media/Filesystem | S3StoragePlugin | — | — | — |

### AWS SDK

AWS SDK として AsyncAWS を採用。詳細は [serverless.md](serverless.md) を参照。

## サーバーレス環境対応

### 設計方針

- Lambda・Cloud Functions・Azure Functions 等のサーバーレス環境をファーストクラスでサポート
- ローカル / サーバーフル環境でも動作する（グレースフルフォールバック）
- 環境変数でクラウドサービスの利用を切り替え

### 対応状況

| 機能 | サーバーレス（本番） | フォールバック（開発/サーバーフル） |
|------|---------------------|-----------------------------------|
| 非同期メッセージ | SQS → Lambda (Bref) | 同期実行 |
| スケジュール | EventBridge → SQS → Lambda | Action Scheduler + WP-Cron |
| ストレージ | S3 (Pre-signed URL) | ローカルファイルシステム |
| メール | SES | wp_mail() デフォルト |

### AWS サーバーレスの詳細

各サービスのフロー図・実装詳細は [serverless.md](serverless.md) を参照。
