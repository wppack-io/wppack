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
| [EventDispatcher](./event-dispatcher/) | `wppack/event-dispatcher` | PSR-14 準拠のイベントシステム |
| [Filesystem](./filesystem/) | `wppack/filesystem` | `WP_Filesystem` DI ラッパー、ファイル操作抽象化 |
| [Kernel](./kernel/) | `wppack/kernel` | アプリケーションブートストラップ |
| [Option](./option/) | `wppack/option` | `wp_options` の型安全ラッパー |
| [Transient](./transient/) | `wppack/transient` | Transient API の型安全ラッパー |
| [Role](./role.md) | `wppack/role` | ロール・権限管理 |
| [Templating](./templating.md) | `wppack/templating` | テンプレートエンジン抽象化 |
| [Stopwatch](./stopwatch.md) | `wppack/stopwatch` | コード実行時間の計測 |
| [Logger](./logger/) | `wppack/logger` | PSR-3 準拠ロガー |
| [MonologLogger](./logger/monolog-logger.md) | `wppack/monolog-logger` | Monolog ブリッジ |

## Abstraction Layer（抽象化層）

WordPress API やデータアクセスを抽象化し、テスト可能にする。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Cache](./cache/) | `wppack/cache` | PSR-6/PSR-16 キャッシュ抽象化 |
| [Database](./database/) | `wppack/database` | `$wpdb` の型安全ラッパー、マイグレーション |
| [Query](./query/) | `wppack/query` | `WP_Query` ビルダー |
| [Security](./security/) | `wppack/security` | 認証・認可フレームワーク |
| [SamlSecurity](./security/saml-security.md) | `wppack/saml-security` | SAML 2.0 SP 認証ブリッジ |
| [OAuthSecurity](./security/oauth-security.md) | `wppack/oauth-security` | OAuth 2.0 / OpenID Connect 認証ブリッジ |
| [Sanitizer](./sanitizer/) | `wppack/sanitizer` | 入力サニタイズ |
| [Escaper](./escaper/) | `wppack/escaper` | 出力エスケープ |
| [HttpClient](./http-client/) | `wppack/http-client` | HTTP クライアント抽象化 |
| [HttpFoundation](./http-foundation/) | `wppack/http-foundation` | Request/Response 抽象化 |
| [Mailer](./mailer/) | `wppack/mailer` | メール送信抽象化、TransportInterface |
| [Messenger](./messenger.md) | `wppack/messenger` | 非同期メッセージバス（SQS/Lambda） |
| [OptionsResolver](./options-resolver/) | `wppack/options-resolver` | オプション解決（Symfony OptionsResolver 拡張） |
| [Debug](./debug/) | `wppack/debug` | デバッグツール |

## Feature Layer（機能層）

WordPress の機能領域をモダンなパターンで扱う。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Admin](./admin/) | `wppack/admin` | 管理画面ページ・メニュー登録 |
| [Rest](./rest/) | `wppack/rest` | REST API エンドポイント定義 |
| [Routing](./routing/) | `wppack/routing` | URL ルーティング |
| [PostType](./post-type.md) | `wppack/post-type` | カスタム投稿タイプ・メタ登録 |
| [Scheduler](./scheduler.md) | `wppack/scheduler` | スケジュール定義（EventBridge 同期） |
| [Command](./command.md) | `wppack/command` | WP-CLI コマンド登録 |
| [Shortcode](./shortcode/) | `wppack/shortcode` | ショートコード登録 |
| [Nonce](./nonce/) | `wppack/nonce` | Nonce 管理 |
| [Ajax](./ajax/) | `wppack/ajax` | Admin Ajax ハンドラー |

## Application Layer（アプリケーション層）

WordPress のアプリケーション構成要素を抽象化する。

| コンポーネント | パッケージ | 説明 |
|---------------|-----------|------|
| [Plugin](./plugin/) | `wppack/plugin` | プラグインライフサイクル管理 |
| [Theme](./theme/) | `wppack/theme` | テーマ機能 |
| [Widget](./widget/) | `wppack/widget` | ウィジェット定義 |
| [Setting](./setting/) | `wppack/setting` | Settings API ラッパー |
| [User](./user.md) | `wppack/user` | ユーザー管理 |
| [Block](./block.md) | `wppack/block` | ブロックエディタ統合 |
| [Media](./media.md) | `wppack/media` | メディア管理 |
| [Comment](./comment.md) | `wppack/comment` | コメント管理 |
| [Taxonomy](./taxonomy.md) | `wppack/taxonomy` | タクソノミー定義 |
| [NavigationMenu](./navigation-menu/) | `wppack/navigation-menu` | ナビゲーションメニュー |
| [Feed](./feed/) | `wppack/feed` | RSS/Atom フィード |
| [OEmbed](./oembed/) | `wppack/oembed` | oEmbed プロバイダー |
| [SiteHealth](./site-health/) | `wppack/site-health` | サイトヘルス |
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

**許可される依存:**
- 自身より下位のレイヤーへの依存

**禁止される依存:**
- Infrastructure が Feature や Application に依存する
- Feature が Application に依存する
- 同一レイヤー内の循環依存

**推奨:**
- インターフェースへの依存を優先する
- 具体クラスへの直接依存は最小限にする
