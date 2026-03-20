# Attribute 一覧

WpPack の全コンポーネントで使用される PHP Attribute の包括的なカタログです。

## 目次

1. [サービス登録 Attributes](#1-サービス登録-attributes)
2. [設定・DI Attributes](#2-設定di-attributes)
3. [Hook Attributes（汎用）](#3-hook-attributes汎用)
4. [Named Hook Attributes（コンポーネント別）](#4-named-hook-attributesコンポーネント別)
5. [データ定義 Attributes](#5-データ定義-attributes)
6. [バリデーション Attributes](#6-バリデーション-attributes)
7. [セキュリティ Attributes](#7-セキュリティ-attributes)
8. [ルーティング Attributes](#8-ルーティング-attributes)
9. [パフォーマンス Attributes](#9-パフォーマンス-attributes)
10. [CLI Attributes](#10-cli-attributes)

## 1. サービス登録 Attributes

コンポーネントやサービスをDIコンテナに登録するための Attribute。

| Attribute | パラメータ | 提供元 | 説明 |
|-----------|-----------|--------|------|
| `#[AsAlias]` | `id: string` | DependencyInjection | インターフェースを実装クラスにバインド（`IS_REPEATABLE`） |
| `#[Exclude]` | _(なし)_ | DependencyInjection | ディレクトリスキャンからクラスを除外 |
| `#[AsHookSubscriber]` | _(なし)_ | Hook | WordPress フック購読クラスとして登録 |
| `#[AsEventListener]` | `event?: string`, `method?: string`, `priority?: int = 10`, `acceptedArgs?: int = 1` | EventDispatcher | PSR-14 イベントリスナーとして登録。クラスまたはメソッドに付与可能（`IS_REPEATABLE`） |
| `#[AsMessageHandler]` | `bus?: string`, `fromTransport?: string`, `handles?: string`, `method?: string`, `priority?: int = 0` | Messenger | メッセージハンドラーとして登録。クラスまたはメソッドに付与可能（`IS_REPEATABLE`） |
| `#[AsSchedule]` | `name?: string = 'default'` | Scheduler | スケジュールプロバイダーとして登録 |
| `#[AsHealthCheck]` | `id: string`, `label: string`, `category: string`, `async?: bool = false` | SiteHealth | サイトヘルスチェックとして登録。WordPress Site Health API にマップ |
| `#[AsDebugInfo]` | `section: string`, `label: string`, `description?: string`, `showCount?: bool = false`, `private?: bool = false` | SiteHealth | サイトヘルスのデバッグ情報セクションとして登録 |
| `#[AsDashboardWidget]` | `id: string`, `label: string`, `context?: string = 'normal'`, `priority?: string = 'core'` | DashboardWidget | ダッシュボードウィジェットとして登録。`wp_add_dashboard_widget()` にマップ |
| `#[AsShortcode]` | `name: string`, `description?: string = ''` | Shortcode | ショートコードとして登録。`add_shortcode()` にマップ |
| `#[AsCommand]` | `name: string`, `description?: string = ''`, `usage?: string = ''` | Console | WP-CLI コマンドとして登録。`WP_CLI::add_command()` にマップ |
| `#[AsAdminPage]` | `slug: string`, `label: string`, `menuLabel?: string = ''`, `parent?: string`, `icon?: string`, `position?: int` | Admin | 管理画面ページとして登録。`add_menu_page()` / `add_submenu_page()` にマップ |
| `#[AsSettingsPage]` | `slug: string`, `label: string`, `menuLabel?: string = ''`, `optionName?: string = ''`, `optionGroup?: string = ''`, `parent?: string = 'options-general.php'`, `icon?: string`, `position?: int` | Setting | 設定ページとして登録。`add_options_page()` にマップ |
| `#[AsWidget]` | `id: string`, `label: string`, `description?: string = ''` | Widget | ウィジェットとして登録。`register_widget()` にマップ |
| `#[AsFeed]` | `slug: string`, `label?: string = ''` | Feed | カスタムフィードとして登録。`add_feed()` にマップ |
| `#[AsDataCollector]` | `name: string`, `priority?: int = 0` | Debug | デバッグデータコレクターとして登録 |
| `#[AsPanelRenderer]` | `name: string`, `priority?: int = 0` | Debug | デバッグパネルレンダラーとして登録 |
| `#[AsAuthenticator]` | `priority?: int = 0` | Security | 認証プロバイダーとして登録 |
| `#[AsVoter]` | `priority?: int = 0` | Security | 認可ボーターとして登録 |

## 2. 設定・DI Attributes

設定値の注入やコンフィグレーションクラスの定義に使用。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[Autowire]` | `env?: string`, `param?: string`, `service?: string`, `option?: string`, `constant?: string` | DependencyInjection | コンストラクタパラメータの自動注入 | - |
| `#[Env]` | `name: string` | DependencyInjection | 環境変数から値を取得（`Autowire(env:)` のショートハンド） | `$_ENV` / `getenv()` |
| `#[Option]` | `name: string` | DependencyInjection | WordPress オプションから値を取得（`Autowire(option:)` のショートハンド） | `get_option()` |
| `#[Constant]` | `name: string` | DependencyInjection | PHP 定数から値を取得（`Autowire(constant:)` のショートハンド） | `defined()` / `constant()` |

## 3. Hook Attributes（汎用）

任意の WordPress フックに対応する汎用 Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[Action]` | `hook: string`, `priority?: int = 10` | Hook | 任意のアクションフックを購読（`IS_REPEATABLE`） | `add_action()` |
| `#[Filter]` | `hook: string`, `priority?: int = 10` | Hook | 任意のフィルターフックを購読（`IS_REPEATABLE`） | `add_filter()` |

## 4. Named Hook Attributes（コンポーネント別）

→ 完全な一覧は [Named Hook 連携規約](../components/hook/named-hook-conventions.md) を参照してください。

WordPress のフックに直接対応する名前付き Attribute。全て `priority?: int = 10` パラメータを持ち、`WpPack\Component\Hook\Attribute\` 名前空間に統合されています。

## 5. データ定義 Attributes

WordPress のデータ構造を定義するための Attribute。

### データベース（Database コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Table]` | `name: string` | `dbDelta()` |

### 翻訳（Translation コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[PluginTextDomain]` | `domain: string`, `path?: string = ''` | `load_plugin_textdomain()` |
| `#[ThemeTextDomain]` | `domain: string`, `path?: string = 'languages'` | `load_theme_textdomain()` |

### ロガー（Logger コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[LoggerChannel]` | `channel: string` | - |

## 6. バリデーション Attributes

> 現在、バリデーション専用の Attribute は未実装です。今後の開発で追加予定です。

## 7. セキュリティ Attributes

認証・認可に使用する Attribute。

| Attribute | パラメータ | 提供元 | 説明 | WordPress API |
|-----------|-----------|--------|------|--------------|
| `#[IsGranted]` | `attribute: string`, `subject?: mixed`, `message?: string = 'Access Denied.'`, `statusCode?: int = 403` | Role | 認可チェック。クラスまたはメソッドに付与可能（`IS_REPEATABLE`）。複数指定で AND（すべて通過が必要） | `current_user_can()` |
| `#[CurrentUser]` | _(なし)_ | Security | コントローラーメソッドの引数に現在のログインユーザー（`WP_User`）を注入 | `wp_get_current_user()` |

`#[IsGranted]` は以下のコンポーネントで使用される capability チェックアトリビュート。各コンポーネントが持っていた `capability` パラメータを置き換える。コンポーネントごとにチェック方式が異なる:

| コンポーネント | チェック方式 |
|--------------|------------|
| Admin / Setting | `IsGrantedChecker::extractCapability()` で文字列を取り出し `add_menu_page()` に渡す（WordPress が制御） |
| Ajax / Routing | ハンドラー実行前に `IsGrantedChecker::check()` でランタイムチェック |
| Rest | `permission_callback` クロージャを生成して `current_user_can()` チェック |
| DashboardWidget | `register()` 内で `current_user_can()` チェック |

> `#[AsAuthenticator]` と `#[AsVoter]` はサービス登録 Attribute として [Section 1](#1-サービス登録-attributes) に記載しています。

## 8. ルーティング Attributes

URL ルーティングと REST API エンドポイントの定義に使用する Attribute。

### REST API（Rest コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[RestRoute]` | `route?: string = ''`, `methods?: HttpMethod\|string\|array = []`, `namespace?: string` | `register_rest_route()` |
| `#[Param]` | `description?: string`, `enum?: array`, `minimum?: int`, `maximum?: int`, `minLength?: int`, `maxLength?: int`, `pattern?: string`, `format?: string`, `items?: string`, `validate?: string`, `sanitize?: string` | REST API Schema |
| `#[Permission]` | `callback?: string`, `public?: bool = false` | `permission_callback` |

`#[RestRoute]` はクラスまたはメソッドに付与可能（`IS_REPEATABLE`）。`#[Param]` はメソッド引数に付与。`#[Permission]` はクラスまたはメソッドに付与可能で、`callback` または `public: true` を指定する。capability チェックには `#[IsGranted]` を使用する。

### リライトルール（Routing コンポーネント）

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Route]` | `name: string`, `regex: string`, `query: string`, `position?: RoutePosition = RoutePosition::Top` | `add_rewrite_rule()` |
| `#[RewriteTag]` | `tag: string`, `regex: string` | `add_rewrite_tag()` |

`#[Route]` はクラスまたはメソッドに付与可能。`#[RewriteTag]` はクラス・メソッドに付与可能（`IS_REPEATABLE`）。

### AJAX（Ajax コンポーネント）

Named Hook アトリビュート（`WpAjaxAction`, `WpAjaxNoprivAction`, `CheckAjaxRefererAction`）は [Named Hook 連携規約](../components/hook/named-hook-conventions.md) を参照。

| Attribute | パラメータ | WordPress API |
|-----------|-----------|--------------|
| `#[Ajax]` | `action: string`, `access?: Access = Access::Public`, `checkReferer?: string`, `priority?: int = 10` | `wp_ajax_{action}` / `wp_ajax_nopriv_{action}` |

`#[Ajax]` はメソッドに付与（`IS_REPEATABLE`）。`Access` enum は `Public`（全ユーザー）、`Authenticated`（ログイン済みのみ）、`Guest`（ゲストのみ）を提供。capability チェックには `#[IsGranted]` を使用する。

## 9. パフォーマンス Attributes

> 現在、パフォーマンス専用の Attribute は未実装です。今後の開発で追加予定です。

## 10. CLI Attributes

WP-CLI コマンドの定義に使用する Attribute。

> `#[AsCommand]` はサービス登録 Attribute として [Section 1](#1-サービス登録-attributes) に記載しています。コマンド引数やオプションの定義は、PHP のネイティブ型宣言とリフレクションによって自動解決されます。


