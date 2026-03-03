# Component カタログ

WpPack のコンポーネントパッケージ一覧。各コンポーネントは独立して `composer require` でインストールできます。

## インストール

```bash
composer require wppack/hook
composer require wppack/messenger
# 必要なパッケージだけを選んでインストール
```

## レイヤー構造

コンポーネントは 4 つのレイヤーに分類されます。各レイヤーは **自身より下位のレイヤーのみ** に依存できます。

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
| [Hook](./hook.md) | `wppack/hook` | アトリビュートベースの WordPress フック（アクション/フィルター）管理 |
| [DependencyInjection](./dependency-injection.md) | `wppack/dependency-injection` | PSR-11 準拠のサービスコンテナ、オートワイヤリング、設定管理 |
| [EventDispatcher](./event-dispatcher.md) | `wppack/event-dispatcher` | PSR-14 準拠のイベントシステム |
| [Filesystem](./filesystem/) | `wppack/filesystem` | `WP_Filesystem` DI ラッパー、ファイル操作抽象化 |
| Kernel | `wppack/kernel` | アプリケーションブートストラップ |
| Option | `wppack/option` | `wp_options` の型安全ラッパー |
| Transient | `wppack/transient` | Transient API の型安全ラッパー |
| Role | `wppack/role` | ロール・権限管理 |
| Templating | `wppack/templating` | テンプレートエンジン抽象化 |
| Logger | `wppack/logger` | PSR-3 準拠ロガー |

## Abstraction Layer（抽象化層）

WordPress API やデータアクセスを抽象化し、テスト可能にする。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| Cache | `wppack/cache` | PSR-6/PSR-16 キャッシュ抽象化 |
| Database | `wppack/database` | `$wpdb` の型安全ラッパー、マイグレーション |
| Query | `wppack/query` | `WP_Query` ビルダー |
| Security | `wppack/security` | セキュリティユーティリティ |
| [Sanitizer](./sanitizer/) | `wppack/sanitizer` | 入力サニタイズ |
| [Escaper](./escaper/) | `wppack/escaper` | 出力エスケープ |
| Validator | `wppack/validator` | バリデーション |
| [HttpClient](./http-client/) | `wppack/http-client` | HTTP クライアント抽象化 |
| HttpFoundation | `wppack/http-foundation` | Request/Response 抽象化 |
| [Mailer](./mailer/) | `wppack/mailer` | メール送信抽象化、TransportInterface |
| [Messenger](./messenger.md) | `wppack/messenger` | 非同期メッセージバス（SQS/Lambda） |
| Debug | `wppack/debug` | デバッグツール |

## Feature Layer（機能層）

WordPress の機能領域をモダンなパターンで扱う。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| Admin | `wppack/admin` | 管理画面ページ・メニュー登録 |
| Rest | `wppack/rest` | REST API エンドポイント定義 |
| Routing | `wppack/routing` | URL ルーティング |
| PostType | `wppack/post-type` | カスタム投稿タイプ・メタ登録 |
| [Scheduler](./scheduler.md) | `wppack/scheduler` | スケジュール定義（EventBridge 同期） |
| Command | `wppack/command` | WP-CLI コマンド登録 |
| Shortcode | `wppack/shortcode` | ショートコード登録 |
| Nonce | `wppack/nonce` | Nonce 管理 |
| [Ajax](./ajax/) | `wppack/ajax` | Admin Ajax ハンドラー |

## Application Layer（アプリケーション層）

WordPress のアプリケーション構成要素を抽象化する。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| Plugin | `wppack/plugin` | プラグインライフサイクル管理 |
| Theme | `wppack/theme` | テーマ機能 |
| [Widget](./widget/) | `wppack/widget` | ウィジェット定義 |
| Setting | `wppack/setting` | Settings API ラッパー |
| User | `wppack/user` | ユーザー管理 |
| Block | `wppack/block` | ブロックエディタ統合 |
| [Media](./media.md) | `wppack/media` | メディア管理 |
| Comment | `wppack/comment` | コメント管理 |
| Taxonomy | `wppack/taxonomy` | タクソノミー定義 |
| NavigationMenu | `wppack/navigation-menu` | ナビゲーションメニュー |
| Feed | `wppack/feed` | RSS/Atom フィード |
| OEmbed | `wppack/oembed` | oEmbed プロバイダー |
| SiteHealth | `wppack/site-health` | サイトヘルス |
| DashboardWidget | `wppack/dashboard-widget` | ダッシュボードウィジェット |
| Translation | `wppack/translation` | 翻訳・国際化 |

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

**許可される依存:**
- 自身より下位のレイヤーへの依存

**禁止される依存:**
- Infrastructure が Feature や Application に依存する
- Feature が Application に依存する
- 同一レイヤー内の循環依存

**推奨:**
- インターフェースへの依存を優先する
- 具体クラスへの直接依存は最小限にする
