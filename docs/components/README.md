# Component カタログ

WpPack のコンポーネントパッケージ一覧。各コンポーネントは独立して `composer require` でインストールできます。

## インストール

```bash
composer require wppack/hook
composer require wppack/messenger
# 必要なパッケージだけを選んでインストール
```

## レイヤー構造

コンポーネントは 4 つのレイヤーに分類されます。

```
Application  → Feature, Abstraction, Infrastructure
Feature      → Abstraction, Infrastructure
Abstraction  → Infrastructure
Infrastructure → (外部ライブラリのみ)
```

## Infrastructure Layer（インフラ層）

WordPress の基盤機能をラップし、型安全でテスタブルなインターフェースを提供する。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Handler](./handler/) | `wppack/handler` | モダン PHP リクエストハンドラー（フロントコントローラー） |
| [Hook](./hook/) | `wppack/hook` | アトリビュートベースの WordPress フック（アクション/フィルター）管理 |
| [DependencyInjection](./dependency-injection/) | `wppack/dependency-injection` | PSR-11 準拠のサービスコンテナ、オートワイヤリング、設定管理 |
| [EventDispatcher](./event-dispatcher/) | `wppack/event-dispatcher` | PSR-14 準拠のイベントシステム |
| [Filesystem](./filesystem/) | `wppack/filesystem` | `WP_Filesystem` DI ラッパー、ファイル操作抽象化 |
| [Kernel](./kernel/) | `wppack/kernel` | アプリケーションブートストラップ、`#[TextDomain]` アトリビュート |
| [Option](./option/) | `wppack/option` | `wp_options` の型安全ラッパー |
| [Transient](./transient/) | `wppack/transient` | Transient API の型安全ラッパー |
| [Role](./role/) | `wppack/role` | ロール・権限管理 |
| [Templating](./templating/) | `wppack/templating` | テンプレートエンジン抽象化 |
| [Stopwatch](./stopwatch/) | `wppack/stopwatch` | コード実行時間の計測 |
| [Logger](./logger/) | `wppack/logger` | PSR-3 準拠ロガー |
| [TwigTemplating](./templating/twig-templating.md) | `wppack/twig-templating` | Twig ブリッジ |
| [MonologLogger](./logger/monolog-logger.md) | `wppack/monolog-logger` | Monolog ブリッジ |
| [Monitoring](./monitoring/) | `wppack/monitoring` | インフラモニタリング抽象化 |
| [CloudWatchMonitoring](./monitoring/cloudwatch-monitoring.md) | `wppack/cloudwatch-monitoring` | AWS CloudWatch モニタリングブリッジ |
| [CloudflareMonitoring](./monitoring/cloudflare-monitoring.md) | `wppack/cloudflare-monitoring` | Cloudflare Analytics モニタリングブリッジ |
| [Mime](./mime/) | `wppack/mime` | MIME 型判定・拡張子マッピング |
| [Site](./site/) | `wppack/site` | マルチサイト管理（ブログ切替・コンテキスト・サイト照会） |

## Abstraction Layer（抽象化層）

WordPress API やデータアクセスを抽象化し、テスト可能にする。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Cache](./cache/) | `wppack/cache` | PSR-6/PSR-16 キャッシュ抽象化 |
| [RedisCache](./cache/redis-cache.md) | `wppack/redis-cache` | Redis / Valkey キャッシュ |
| [ElastiCacheAuth](./cache/elasticache-auth.md) | `wppack/elasticache-auth` | ElastiCache IAM 認証 |
| [DynamoDbCache](./cache/dynamodb-cache.md) | `wppack/dynamodb-cache` | DynamoDB キャッシュ |
| [MemcachedCache](./cache/memcached-cache.md) | `wppack/memcached-cache` | Memcached キャッシュ |
| [ApcuCache](./cache/apcu-cache.md) | `wppack/apcu-cache` | APCu キャッシュ |
| [Database](./database/) | `wppack/database` | `$wpdb` の型安全ラッパー、Driver/Platform/Connection 抽象化 |
| [SqliteDatabase](./database/) | `wppack/sqlite-database` | SQLite データベースドライバ |
| [PgsqlDatabase](./database/) | `wppack/pgsql-database` | PostgreSQL データベースドライバ |
| [MysqlDataApiDatabase](./database/) | `wppack/mysql-data-api-database` | Aurora MySQL Data API ドライバ |
| [PgsqlDataApiDatabase](./database/) | `wppack/pgsql-data-api-database` | Aurora PostgreSQL Data API ドライバ |
| [AuroraDsqlDatabase](./database/) | `wppack/aurora-dsql-database` | Aurora DSQL データベースドライバ |
| [Dsn](./dsn.md) | `wppack/dsn` | 共通 DSN パーサー |
| [DatabaseExport](./database-export.md) | `wppack/database-export` | データベースエクスポート（SQL/JSON/CSV） |
| [Query](./query/) | `wppack/query` | `WP_Query` ビルダー |
| [Security](./security/) | `wppack/security` | 認証・認可フレームワーク |
| [SamlSecurity](./security/saml-security.md) | `wppack/saml-security` | SAML 2.0 SP 認証ブリッジ |
| [OAuthSecurity](./security/oauth-security.md) | `wppack/oauth-security` | OAuth 2.0 / OpenID Connect 認証ブリッジ |
| [PasskeySecurity](./security/passkey-security.md) | `wppack/passkey-security` | WebAuthn/Passkey 認証ブリッジ |
| [Sanitizer](./sanitizer/) | `wppack/sanitizer` | 入力サニタイズ |
| [Escaper](./escaper/) | `wppack/escaper` | 出力エスケープ |
| [HttpClient](./http-client/) | `wppack/http-client` | HTTP クライアント抽象化 |
| [HttpFoundation](./http-foundation/) | `wppack/http-foundation` | Request/Response 抽象化 |
| [Mailer](./mailer/) | `wppack/mailer` | メール送信抽象化、TransportInterface |
| [AmazonMailer](./mailer/amazon-mailer.md) | `wppack/amazon-mailer` | SES トランスポート実装 |
| [AzureMailer](./mailer/azure-mailer.md) | `wppack/azure-mailer` | Azure Communication Services トランスポート実装 |
| [SendGridMailer](./mailer/sendgrid-mailer.md) | `wppack/sendgrid-mailer` | SendGrid トランスポート実装 |
| [Messenger](./messenger/) | `wppack/messenger` | トランスポート非依存のメッセージバス |
| [SqsMessenger](./messenger/sqs-messenger.md) | `wppack/sqs-messenger` | Amazon SQS トランスポート |
| [Serializer](./serializer/) | `wppack/serializer` | オブジェクト直列化（Normalizer チェーン） |
| [OptionsResolver](./options-resolver/) | `wppack/options-resolver` | オプション解決（Symfony OptionsResolver 拡張） |
| [Debug](./debug/) | `wppack/debug` | デバッグ・プロファイリング |
| [Storage](./storage/) | `wppack/storage` | オブジェクトストレージ抽象化 |
| [S3Storage](./storage/s3-storage.md) | `wppack/s3-storage` | Amazon S3 ストレージアダプタ |
| [AzureStorage](./storage/azure-storage.md) | `wppack/azure-storage` | Azure Blob Storage アダプタ |
| [GcsStorage](./storage/gcs-storage.md) | `wppack/gcs-storage` | Google Cloud Storage アダプタ |

## Feature Layer（機能層）

WordPress の機能領域をモダンなパターンで扱う。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Admin](./admin/) | `wppack/admin` | 管理画面ページ・メニュー登録 |
| [Rest](./rest/) | `wppack/rest` | REST API エンドポイント定義 |
| [Routing](./routing/) | `wppack/routing` | URL ルーティング |
| [PostType](./post-type/) | `wppack/post-type` | カスタム投稿タイプ・メタ登録 |
| [Scheduler](./scheduler/) | `wppack/scheduler` | Trigger ベースのタスクスケジューラー |
| [EventBridgeScheduler](./scheduler/eventbridge-scheduler.md) | `wppack/eventbridge-scheduler` | EventBridge スケジューラー |
| [Console](./console/) | `wppack/console` | WP-CLI コマンドフレームワーク |
| [Shortcode](./shortcode/) | `wppack/shortcode` | ショートコード登録 |
| [Nonce](./nonce/) | `wppack/nonce` | CSRF トークン管理 |
| [Asset](./asset/) | `wppack/asset` | アセット管理（スクリプト・スタイル） |
| [Ajax](./ajax/) | `wppack/ajax` | Admin Ajax ハンドラー |
| [Scim](./scim/) | `wppack/scim` | SCIM 2.0 プロビジョニング |
| [Wpress](./wpress/) | `wppack/wpress` | .wpress アーカイブ形式操作 |

## Application Layer（アプリケーション層）

WordPress のアプリケーション構成要素を抽象化する。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Plugin](./plugin/) | `wppack/plugin` | プラグインライフサイクル管理 |
| [Theme](./theme/) | `wppack/theme` | テーマ開発フレームワーク |
| [Widget](./widget/) | `wppack/widget` | ウィジェット定義 |
| [Setting](./setting/) | `wppack/setting` | Settings API ラッパー |
| [User](./user/) | `wppack/user` | ユーザー管理 |
| [Block](./block.md) | `wppack/block` | ブロックエディタ統合 |
| [Media](./media/) | `wppack/media` | メディア管理 |
| [Comment](./comment.md) | `wppack/comment` | コメント管理 |
| [Taxonomy](./taxonomy/) | `wppack/taxonomy` | タクソノミー定義 |
| [NavigationMenu](./navigation-menu/) | `wppack/navigation-menu` | メニュー管理 |
| [Feed](./feed/) | `wppack/feed` | RSS/Atom フィード |
| [OEmbed](./oembed/) | `wppack/oembed` | oEmbed プロバイダー |
| [SiteHealth](./site-health/) | `wppack/site-health` | サイトヘルスチェック |
| [DashboardWidget](./dashboard-widget/) | `wppack/dashboard-widget` | ダッシュボードウィジェット |
| [Translation](./translation/) | `wppack/translation` | 翻訳・国際化 |

## 名前空間

すべてのコンポーネントは `WpPack\Component\{Name}\` 名前空間を使用します:

```
WpPack\Component\Hook\
WpPack\Component\Messenger\
WpPack\Component\Scheduler\
WpPack\Component\Mailer\
...
```

## レイヤー間の依存ルール

- インターフェースへの依存を優先し、具体クラスへの直接依存は最小限にする
