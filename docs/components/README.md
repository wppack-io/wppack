# Component カタログ

WPPack のコンポーネントパッケージ一覧。各コンポーネントは独立して `composer require` でインストールできます。

## インストール

```bash
composer require wppack/hook
composer require wppack/messenger
# 必要なパッケージだけを選んでインストール
```

## 分類の考え方

コンポーネントは **WordPress 開発者の関心ドメイン** で 8 カテゴリに分けます。
「層」ではなく「役割タグ」です。依存ルールはただ 1 つ — **サイクル禁止**。
Substrate が下地で他カテゴリから自由に依存されますが、それ以外は意味的に
妥当なら横方向に参照してかまいません。

| # | カテゴリ | 役割 |
|---|----------|------|
| 1 | [Substrate (基盤)](#1-substrate-基盤) | ランタイム・観測・非同期配送 |
| 2 | [Data (データ)](#2-data-データ) | WP が扱うデータストアのラッパー |
| 3 | [Content (コンテンツ)](#3-content-コンテンツ) | WP のコンテンツ種別 |
| 4 | [Identity & Security (認証・認可・セキュリティ)](#4-identity--security-認証認可セキュリティ) | ユーザ / 権限 / 境界 / 保護 |
| 5 | [HTTP (リクエスト・レスポンス)](#5-http-リクエストレスポンス) | HTTP 入出力・ルーティング |
| 6 | [Presentation (表示)](#6-presentation-表示) | フロントエンド UI・テンプレート |
| 7 | [Admin (管理画面)](#7-admin-管理画面) | 管理画面の UI と dispatch |
| 8 | [Utility (汎用ユーティリティ)](#8-utility-汎用ユーティリティ) | どのカテゴリにも属さない汎用ツール |

## 1. Substrate (基盤)

WP ランタイムの下地。Kernel / DI / Hook / Events のブートストラップ、
観測 (Logger / Monitoring / Debug / Stopwatch)、そして WP-Cron / wp_mail
の置き換えとなる非同期配送プリミティブ (Messenger / Scheduler / Mailer)
を含む。外部サービスへのブリッジはこの層の optional アップグレード。

### 1a. Runtime (ランタイム)

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Handler](./handler/) | `wppack/handler` | モダン PHP リクエストハンドラー（フロントコントローラー） |
| [Hook](./hook/) | `wppack/hook` | アトリビュートベースの WordPress フック（アクション/フィルター）管理 |
| [DependencyInjection](./dependency-injection/) | `wppack/dependency-injection` | PSR-11 準拠のサービスコンテナ、オートワイヤリング、設定管理 |
| [EventDispatcher](./event-dispatcher/) | `wppack/event-dispatcher` | PSR-14 準拠のイベントシステム |
| [Kernel](./kernel/) | `wppack/kernel` | アプリケーションブートストラップ、`#[TextDomain]` アトリビュート |
| [Plugin](./plugin/) | `wppack/plugin` | プラグインライフサイクル管理 |
| [Console](./console/) | `wppack/console` | WP-CLI コマンドフレームワーク |
| [Filesystem](./filesystem/) | `wppack/filesystem` | `WP_Filesystem` DI ラッパー、ファイル操作抽象化 |

### 1b. Observability (観測)

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Logger](./logger/) | `wppack/logger` | PSR-3 準拠ロガー |
| [MonologLogger](./logger/monolog-logger.md) | `wppack/monolog-logger` | Monolog ブリッジ |
| [Debug](./debug/) | `wppack/debug` | デバッグ・プロファイリング |
| [Stopwatch](./stopwatch/) | `wppack/stopwatch` | コード実行時間の計測 |
| [Monitoring](./monitoring/) | `wppack/monitoring` | インフラモニタリング抽象化 |
| [CloudWatchMonitoring](./monitoring/cloudwatch-monitoring.md) | `wppack/cloudwatch-monitoring` | AWS CloudWatch モニタリングブリッジ |
| [CloudflareMonitoring](./monitoring/cloudflare-monitoring.md) | `wppack/cloudflare-monitoring` | Cloudflare Analytics モニタリングブリッジ |

### 1c. Async & Delivery (非同期・配送) — WP-Cron / wp_mail の置き換え

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Messenger](./messenger/) | `wppack/messenger` | トランスポート非依存のメッセージバス（WP-Cron の async 置換） |
| [SqsMessenger](./messenger/sqs-messenger.md) | `wppack/sqs-messenger` | Amazon SQS トランスポート |
| [Scheduler](./scheduler/) | `wppack/scheduler` | Trigger ベースのタスクスケジューラー（WP-Cron の scheduled 置換） |
| [EventBridgeScheduler](./scheduler/eventbridge-scheduler.md) | `wppack/eventbridge-scheduler` | EventBridge スケジューラーブリッジ |
| [Mailer](./mailer/) | `wppack/mailer` | メール送信抽象化（`wp_mail` 置換）、TransportInterface |
| [AmazonMailer](./mailer/amazon-mailer.md) | `wppack/amazon-mailer` | SES トランスポート実装 |
| [AzureMailer](./mailer/azure-mailer.md) | `wppack/azure-mailer` | Azure Communication Services トランスポート実装 |
| [SendGridMailer](./mailer/sendgrid-mailer.md) | `wppack/sendgrid-mailer` | SendGrid トランスポート実装 |

## 2. Data (データ)

WP が扱うデータストアの型安全ラッパー。`$wpdb` / `wp_options` /
Transient API / Object Cache / アップロードファイルを抽象化する。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Database](./database/) | `wppack/database` | `$wpdb` の型安全ラッパー、Driver/Platform/Connection 抽象化 |
| [SqliteDatabase](./database/) | `wppack/sqlite-database` | SQLite データベースドライバ |
| [PostgreSQLDatabase](./database/) | `wppack/postgresql-database` | PostgreSQL データベースドライバ |
| [MySQLDataApiDatabase](./database/) | `wppack/mysql-data-api-database` | Aurora MySQL Data API ドライバ |
| [PostgreSQLDataApiDatabase](./database/) | `wppack/postgresql-data-api-database` | Aurora PostgreSQL Data API ドライバ |
| [AuroraDSQLDatabase](./database/) | `wppack/aurora-dsql-database` | Aurora DSQL データベースドライバ |
| [Dsn](./dsn.md) | `wppack/dsn` | 共通 DSN パーサー |
| [DatabaseExport](./database-export/) | `wppack/database-export` | データベースエクスポート（SQL/JSON/CSV） |
| [Query](./query/) | `wppack/query` | `WP_Query` ビルダー |
| [Option](./option/) | `wppack/option` | `wp_options` の型安全ラッパー |
| [Transient](./transient/) | `wppack/transient` | Transient API の型安全ラッパー |
| [Cache](./cache/) | `wppack/cache` | PSR-6/PSR-16 キャッシュ抽象化 |
| [RedisCache](./cache/redis-cache.md) | `wppack/redis-cache` | Redis / Valkey キャッシュ |
| [ElastiCacheAuth](./cache/elasticache-auth.md) | `wppack/elasticache-auth` | ElastiCache IAM 認証 |
| [DynamoDbCache](./cache/dynamodb-cache.md) | `wppack/dynamodb-cache` | DynamoDB キャッシュ |
| [MemcachedCache](./cache/memcached-cache.md) | `wppack/memcached-cache` | Memcached キャッシュ |
| [ApcuCache](./cache/apcu-cache.md) | `wppack/apcu-cache` | APCu キャッシュ |
| [Storage](./storage/) | `wppack/storage` | オブジェクトストレージ抽象化 |
| [S3Storage](./storage/s3-storage.md) | `wppack/s3-storage` | Amazon S3 ストレージアダプタ |
| [AzureStorage](./storage/azure-storage.md) | `wppack/azure-storage` | Azure Blob Storage アダプタ |
| [GcsStorage](./storage/gcs-storage.md) | `wppack/gcs-storage` | Google Cloud Storage アダプタ |

## 3. Content (コンテンツ)

WordPress のコンテンツ種別 (posts / taxonomies / comments / media /
menus / blocks / feeds)。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [PostType](./post-type/) | `wppack/post-type` | カスタム投稿タイプ・メタ登録 |
| [Taxonomy](./taxonomy/) | `wppack/taxonomy` | タクソノミー定義 |
| [Comment](./comment.md) | `wppack/comment` | コメント管理 |
| [Media](./media/) | `wppack/media` | メディア管理 |
| [NavigationMenu](./navigation-menu/) | `wppack/navigation-menu` | メニュー管理 |
| [Block](./block.md) | `wppack/block` | ブロックエディタ統合 |
| [Feed](./feed/) | `wppack/feed` | RSS/Atom フィード |
| [OEmbed](./oembed/) | `wppack/oembed` | oEmbed プロバイダー |

## 4. Identity & Security (認証・認可・セキュリティ)

ユーザ、権限、認証プロバイダ、CSRF 保護、マルチサイト境界など「誰が / どこで /
何に触れるか」の境界線を扱う。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [User](./user/) | `wppack/user` | ユーザー管理 |
| [Role](./role/) | `wppack/role` | ロール・権限管理 |
| [Security](./security/) | `wppack/security` | 認証・認可フレームワーク |
| [SamlSecurity](./security/saml-security.md) | `wppack/saml-security` | SAML 2.0 SP 認証ブリッジ |
| [OAuthSecurity](./security/oauth-security.md) | `wppack/oauth-security` | OAuth 2.0 / OpenID Connect 認証ブリッジ |
| [PasskeySecurity](./security/passkey-security.md) | `wppack/passkey-security` | WebAuthn/Passkey 認証ブリッジ |
| [Nonce](./nonce/) | `wppack/nonce` | CSRF トークン管理 |
| [Scim](./scim/) | `wppack/scim` | SCIM 2.0 プロビジョニング |
| [Site](./site/) | `wppack/site` | マルチサイト管理（ブログ切替・コンテキスト・サイト照会） |

## 5. HTTP (リクエスト・レスポンス)

HTTP 入出力のプリミティブと、URL / REST / Ajax / Shortcode の dispatch。
いずれも「key → callback」の register パターンで揃えてある。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [HttpFoundation](./http-foundation/) | `wppack/http-foundation` | Request/Response 抽象化 |
| [HttpClient](./http-client/) | `wppack/http-client` | HTTP クライアント抽象化 |
| [Routing](./routing/) | `wppack/routing` | URL ルーティング |
| [Rest](./rest/) | `wppack/rest` | REST API エンドポイント定義 |
| [Ajax](./ajax/) | `wppack/ajax` | Admin Ajax ハンドラー |
| [Shortcode](./shortcode/) | `wppack/shortcode` | ショートコード登録 |

## 6. Presentation (表示)

フロントエンド表示のテンプレート・アセット・テーマ・ウィジェット、および
出力・入力時のテキスト処理。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Templating](./templating/) | `wppack/templating` | テンプレートエンジン抽象化 |
| [TwigTemplating](./templating/twig-templating.md) | `wppack/twig-templating` | Twig ブリッジ |
| [Asset](./asset/) | `wppack/asset` | アセット管理（スクリプト・スタイル） |
| [Theme](./theme/) | `wppack/theme` | テーマ開発フレームワーク |
| [Widget](./widget/) | `wppack/widget` | ウィジェット定義 |
| [Translation](./translation/) | `wppack/translation` | 翻訳・国際化 |
| [Escaper](./escaper/) | `wppack/escaper` | 出力エスケープ |
| [Sanitizer](./sanitizer/) | `wppack/sanitizer` | 入力サニタイズ |
| [Mime](./mime/) | `wppack/mime` | MIME 型判定・拡張子マッピング |

## 7. Admin (管理画面)

WP 管理画面 (wp-admin) の UI と dispatch。管理メニュー登録、Settings API、
ダッシュボードウィジェット、サイトヘルス。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Admin](./admin/) | `wppack/admin` | 管理画面ページ・メニュー登録 |
| [Setting](./setting/) | `wppack/setting` | Settings API ラッパー |
| [DashboardWidget](./dashboard-widget/) | `wppack/dashboard-widget` | ダッシュボードウィジェット |
| [SiteHealth](./site-health/) | `wppack/site-health` | サイトヘルスチェック |

## 8. Utility (汎用ユーティリティ)

どのカテゴリにも属さない、WordPress ドメイン非依存の汎用ツール。
他カテゴリのコンポーネントから必要に応じて利用される。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Serializer](./serializer/) | `wppack/serializer` | オブジェクト直列化（Normalizer チェーン） |
| [OptionsResolver](./options-resolver/) | `wppack/options-resolver` | オプション解決（Symfony OptionsResolver 拡張） |
| [Wpress](./wpress/) | `wppack/wpress` | .wpress アーカイブ形式操作 |

## 名前空間

すべてのコンポーネントは `WPPack\Component\{Name}\` 名前空間を使用します:

```
WPPack\Component\Hook\
WPPack\Component\Messenger\
WPPack\Component\Scheduler\
WPPack\Component\Mailer\
...
```

## 依存関係ルール

- **Substrate** と **Utility** はどのカテゴリからでも自由に依存してよい
- **Data / Content / Identity & Security / HTTP / Presentation / Admin** は横並び。意味的に妥当なら相互参照可
- **サイクル禁止** — 依存グラフは DAG に保つ
- **Bridge パッケージ**は対応する親コンポーネントのみに依存する
- インターフェースへの依存を優先し、具体クラスへの直接依存は最小限にする
