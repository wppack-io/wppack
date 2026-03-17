# アーキテクチャ概要

## 設計思想

WpPack は **コンポーネントライブラリ** です。フレームワークではありません。

- 必要なパッケージだけを `composer require` して使う
- 各コンポーネントは独立して動作する（最小限の依存）
- Symfony のパターンにインスパイアされつつ、WordPress の仕組みを尊重する
- WordPress の Hook システムやグローバル関数との共存を前提とする

## レイヤー構造

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Plugin Layer                                                                │
│   EventBridgeSchedulerPlugin, S3StoragePlugin, AmazonMailerPlugin                      │
├─────────────────────────────────────────────────────────────────────────────┤
│ Application Layer                                                           │
│   Plugin, Theme, Widget, Setting, User, Block, Media, Comment,              │
│   Taxonomy, NavigationMenu, Feed, OEmbed, SiteHealth, DashboardWidget,      │
│   Translation                                                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ Feature Layer                                                               │
│   Admin, REST, Routing, PostType, Scheduler, Command, Shortcode,            │
│   Nonce, Ajax                                                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ Abstraction Layer                                                           │
│   Cache, Database, Query, Security, Sanitizer, Validator,                   │
│   HttpClient, HttpFoundation, Mailer, Messenger, Debug                      │
├─────────────────────────────────────────────────────────────────────────────┤
│ Infrastructure Layer                                                        │
│   Hook, DI, Config, EventDispatcher, Filesystem, Kernel,                    │
│   Option, Transient, Role, Templating, Logger                               │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Infrastructure Layer（インフラ層）

WordPress の基盤機能をラップし、型安全で テスタブルなインターフェースを提供する。

| コンポーネント | 概要 |
|---|---|
| Hook | WordPress Hook システムのラッパー |
| DI | 依存性注入コンテナ |
| Config | 設定管理 |
| EventDispatcher | イベントディスパッチャー |
| Filesystem | ファイルシステム操作 |
| Kernel | アプリケーションカーネル |
| Option | `wp_options` のラッパー |
| Transient | Transient API のラッパー |
| Role | ロール・権限管理 |
| Templating | テンプレートエンジン |
| Logger | PSR-3 準拠ロガー |

### Abstraction Layer（抽象化層）

WordPress API やデータアクセスを抽象化し、テスト可能にする。

| コンポーネント | 概要 |
|---|---|
| Cache | PSR-6/PSR-16 キャッシュ |
| Database | `$wpdb` の型安全ラッパー |
| Query | `WP_Query` ビルダー |
| Security | セキュリティユーティリティ |
| Sanitizer | サニタイゼーション |
| Validator | バリデーション |
| HttpClient | HTTP クライアント |
| HttpFoundation | Request/Response 抽象化 |
| Mailer | メール送信 |
| Messenger | メッセージング基盤（SQS 対応） |
| Debug | デバッグツール |

### Feature Layer（機能層）

WordPress の機能領域をモダンなパターンで扱う。

| コンポーネント | 概要 |
|---|---|
| Admin | 管理画面ページ・メニュー |
| REST | REST API エンドポイント定義 |
| Routing | ルーティング |
| PostType | カスタム投稿タイプ・メタ |
| Scheduler | スケジュール定義（EventBridge 同期） |
| Command | WP-CLI コマンド |
| Shortcode | ショートコード |
| Nonce | Nonce 管理 |
| Ajax | Admin Ajax ハンドラー |

### Application Layer（アプリケーション層）

WordPress のアプリケーション構成要素を抽象化する。

| コンポーネント | 概要 |
|---|---|
| Plugin | プラグインライフサイクル管理 |
| Theme | テーマ機能 |
| Widget | ウィジェット定義 |
| Setting | Settings API ラッパー |
| User | ユーザー管理 |
| Block | ブロックエディタ統合 |
| Media | メディア管理 |
| Comment | コメント管理 |
| Taxonomy | タクソノミー定義 |
| NavigationMenu | ナビゲーションメニュー |
| Feed | RSS/Atom フィード |
| OEmbed | oEmbed プロバイダー |
| SiteHealth | サイトヘルス |
| DashboardWidget | ダッシュボードウィジェット |
| Translation | 翻訳・国際化 |

### Plugin Layer（プラグイン層）

複数のコンポーネントを組み合わせた、すぐに使える WordPress プラグイン。

| プラグイン | 概要 |
|---|---|
| EventBridgeSchedulerPlugin | スケジュール管理（Action Scheduler + EventBridge） |
| S3StoragePlugin | S3 メディアストレージ |
| AmazonMailerPlugin | Amazon SES メール送信 |

## 名前空間ルール

### Component（コンポーネント）

```
WpPack\Component\{Name}\
```

例:
- `WpPack\Component\Messenger\MessageBus`
- `WpPack\Component\Scheduler\RecurringMessage`
- `WpPack\Component\Hook\HookManager`

### Plugin（プラグイン）

```
WpPack\Plugin\{Name}\
```

例:
- `WpPack\Plugin\EventBridgeSchedulerPlugin\EventBridgeSchedulerPlugin`
- `WpPack\Plugin\S3StoragePlugin\S3StoragePlugin`

## レイヤー間の依存ルール

```
Plugin       → Application, Feature, Abstraction, Infrastructure
Application  → Feature, Abstraction, Infrastructure
Feature      → Abstraction, Infrastructure
Abstraction  → Infrastructure
Infrastructure → (外部ライブラリのみ)
```

- インターフェースへの依存を優先し、具体クラスへの直接依存は最小限にする
