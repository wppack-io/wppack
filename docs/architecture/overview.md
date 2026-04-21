# アーキテクチャ概要

## 設計思想

WPPack は **コンポーネントライブラリ** です。フレームワークではありません。

- 必要なパッケージだけを `composer require` して使う
- 各コンポーネントは独立して動作する（最小限の依存）
- Symfony のパターンにインスパイアされつつ、WordPress の仕組みを尊重する
- WordPress の Hook システムやグローバル関数との共存を前提とする

## 分類 — 関心ドメインによる 7 カテゴリ

旧 4 レイヤー (Infrastructure / Abstraction / Feature / Application) は
境目が曖昧で「なぜこの区分にあるのか」を説明しづらかったため、**WordPress
開発者の関心ドメイン** に沿った 7 カテゴリ + 汎用 Utility へ整理しなおし
ました。層構造ではなく、意味的なタグです。

```
┌───────────────────────────────────────────────────────────────────────┐
│ 7. Admin (管理画面)                                                   │
│    Admin, Setting, DashboardWidget, SiteHealth                        │
├───────────────────────────────────────────────────────────────────────┤
│ 6. Presentation (表示)                                                │
│    Templating(+Twig), Asset, Theme, Widget, Translation,              │
│    Escaper, Sanitizer, Mime                                           │
├───────────────────────────────────────────────────────────────────────┤
│ 5. HTTP (リクエスト・レスポンス)                                      │
│    HttpFoundation, HttpClient, Routing, Rest, Ajax, Shortcode         │
├───────────────────────────────────────────────────────────────────────┤
│ 4. Identity & Security (認証・認可・セキュリティ)                     │
│    User, Role, Security(+SAML/OAuth/Passkey), Nonce, Scim, Site       │
├───────────────────────────────────────────────────────────────────────┤
│ 3. Content (コンテンツ)                                               │
│    PostType, Taxonomy, Comment, Media, NavigationMenu,                │
│    Block, Feed, OEmbed                                                │
├───────────────────────────────────────────────────────────────────────┤
│ 2. Data (データ)                                                      │
│    Database(+bridges), Option, Transient,                             │
│    Cache(+bridges), Storage(+bridges), Query, DatabaseExport, Dsn     │
├───────────────────────────────────────────────────────────────────────┤
│ 1. Substrate (基盤)                                                   │
│    1a Runtime      — Kernel, Handler, DI, Events, Hook,               │
│                      Plugin, Console, Filesystem                      │
│    1b Observability — Logger(+Monolog), Debug, Stopwatch,             │
│                      Monitoring(+CloudWatch/Cloudflare)               │
│    1c Async&Delivery — Messenger(+SQS), Scheduler(+EventBridge),      │
│                      Mailer(+SES/Azure/SendGrid)                      │
└───────────────────────────────────────────────────────────────────────┘

           ┌─────────────────────────────────────────────┐
           │ 8. Utility (汎用ユーティリティ)             │
           │    Serializer, OptionsResolver, Wpress      │
           └─────────────────────────────────────────────┘
              (どのカテゴリからでも自由に利用される)
```

詳細な所属一覧は [`docs/components/README.md`](../components/README.md) を参照。

### 1. Substrate (基盤)

WP ランタイムの下地。ブートストラップ (Kernel / DI / Events / Hook)、
観測 (Logger / Monitoring / Debug / Stopwatch)、WP-Cron / wp_mail の
置き換え (Messenger / Scheduler / Mailer) を含む。外部サービスの
ブリッジは optional アップグレード (SQS / EventBridge / SES / CloudWatch...)。

### 2. Data (データ)

WP が扱うデータストアの型安全ラッパー。`$wpdb` / `wp_options` /
Transient API / Object Cache / アップロード先オブジェクトストレージ。

### 3. Content (コンテンツ)

WP のコンテンツ種別: posts / taxonomies / comments / media / menus /
blocks / feeds / oEmbed。

### 4. Identity & Security (認証・認可・セキュリティ)

ユーザ / 権限 / 認証プロバイダ / CSRF 保護 / マルチサイト境界 /
プロビジョニング。「誰が / どこで / 何に触れるか」の境界線を扱う。

### 5. HTTP (リクエスト・レスポンス)

HTTP 入出力と、URL / REST / Ajax / Shortcode の dispatch。
いずれも「key → callback」の register パターン。

### 6. Presentation (表示)

フロントエンドのテンプレート・アセット・テーマ・ウィジェット、および
出力・入力時のテキスト処理 (Escaper / Sanitizer / Mime)。

### 7. Admin (管理画面)

wp-admin の UI と dispatch。管理メニュー登録、Settings API、ダッシュ
ボードウィジェット、サイトヘルス。

### 8. Utility (汎用ユーティリティ)

どのカテゴリにも属さない、WordPress ドメイン非依存の汎用ツール
(Serializer / OptionsResolver / Wpress)。他カテゴリから自由に利用される。

## Plugin レイヤー

複数のコンポーネントを組み合わせた、すぐに使える WordPress プラグイン。
`docs/plugins/README.md` に一覧があります。

| プラグイン | 概要 |
|---|---|
| EventBridgeSchedulerPlugin | スケジュール管理（Action Scheduler + EventBridge） |
| S3StoragePlugin | S3 メディアストレージ |
| AmazonMailerPlugin | Amazon SES メール送信 |

## 名前空間ルール

### Component（コンポーネント）

```
WPPack\Component\{Name}\
```

例:
- `WPPack\Component\Messenger\MessageBus`
- `WPPack\Component\Scheduler\RecurringMessage`
- `WPPack\Component\Hook\HookManager`

### Plugin（プラグイン）

```
WPPack\Plugin\{Name}\
```

例:
- `WPPack\Plugin\EventBridgeSchedulerPlugin\EventBridgeSchedulerPlugin`
- `WPPack\Plugin\S3StoragePlugin\S3StoragePlugin`

## 依存関係ルール

- **Substrate** と **Utility** はどのカテゴリからでも自由に依存してよい
- **Data / Content / Identity & Security / HTTP / Presentation / Admin** は横並び。意味的に妥当なら相互参照可
- **サイクル禁止** — 依存グラフは DAG に保つ
- **Bridge パッケージ**は対応する親コンポーネントのみに依存する
- インターフェースへの依存を優先し、具体クラスへの直接依存は最小限にする
