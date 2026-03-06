# WordPress 管理画面 API 仕様

## 1. 概要

WordPress の管理画面（Admin）API は、`wp-admin` の UI を構築・カスタマイズするための仕組みです。メニュー、サブメニュー、管理ページ、メタボックス、ヘルプタブ、スクリーンオプション、ダッシュボードウィジェットなどを管理します。

### 主要コンポーネント

| コンポーネント | クラス / ファイル | 説明 |
|---|---|---|
| `WP_Screen` | `class-wp-screen.php` | 現在の管理画面の情報を管理 |
| メニューシステム | `menu.php`, `admin-header.php` | 管理メニューの登録・表示 |
| メタボックス | `meta-boxes.php` | 投稿編集画面等のメタボックス |
| ダッシュボードウィジェット | `dashboard.php` | ダッシュボードのウィジェット |
| リストテーブル | `WP_List_Table` | 投稿一覧等のテーブル表示 |
| 管理バー | `WP_Admin_Bar` | 画面上部の管理バー |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$menu` | `array` | トップレベルメニュー項目の配列 |
| `$submenu` | `array` | サブメニュー項目の配列（親スラッグがキー） |
| `$_wp_menu_nopriv` | `array` | 権限なしメニューの配列 |
| `$_wp_submenu_nopriv` | `array` | 権限なしサブメニューの配列 |
| `$_registered_pages` | `array` | 登録されたページのフックサフィックス |
| `$_parent_pages` | `array` | ページの親メニューマッピング |
| `$wp_meta_boxes` | `array` | 登録されたメタボックスの配列 |
| `$current_screen` | `WP_Screen` | 現在の管理画面オブジェクト |
| `$wp_admin_bar` | `WP_Admin_Bar` | 管理バーインスタンス |

## 2. データ構造

### WP_Screen クラス

現在の管理画面のコンテキストを表すクラスです。

```php
class WP_Screen {
    public $action;          // アクション（'add', 'edit' 等）
    public $base;            // ベーススクリーン名（'post', 'edit', 'upload' 等）
    public $columns;         // カラム数（ダッシュボード用）
    public $id;              // スクリーン ID（例: 'edit-post', 'dashboard'）
    public $in_admin;        // 管理画面の種類（'site', 'network', 'user'）
    public $is_block_editor; // ブロックエディタ画面か
    public $is_network;      // ネットワーク管理画面か
    public $is_user;         // ユーザー管理画面か
    public $parent_base;     // 親スクリーンのベース名
    public $parent_file;     // 親ファイル名
    public $post_type;       // 投稿タイプ（該当する場合）
    public $taxonomy;        // タクソノミー（該当する場合）

    private $_help_tabs = [];          // ヘルプタブ
    private $_help_sidebar = '';       // ヘルプサイドバー
    private $_screen_settings = '';    // スクリーン設定
    private $_options = [];            // スクリーンオプション
    private $_show_screen_options;     // スクリーンオプション表示フラグ
    private $_screen_reader_content = []; // スクリーンリーダー用テキスト
}
```

### `$menu` 配列の構造

```php
$menu = [
    2  => ['Dashboard', 'read', 'index.php', '', 'menu-top menu-icon-dashboard', 'menu-dashboard', 'dashicons-dashboard'],
    4  => ['', 'read', 'separator1', '', 'wp-menu-separator'],
    5  => ['Posts', 'edit_posts', 'edit.php', '', 'menu-top menu-icon-post', 'menu-posts', 'dashicons-admin-post'],
    10 => ['Media', 'upload_files', 'upload.php', '', 'menu-top menu-icon-media', 'menu-media', 'dashicons-admin-media'],
    20 => ['Pages', 'edit_pages', 'edit.php?post_type=page', '', 'menu-top menu-icon-page', 'menu-pages', 'dashicons-admin-page'],
    // ...
];
// インデックス: [0]メニュー名, [1]権限, [2]スラッグ, [3]ページタイトル, [4]CSSクラス, [5]フックサフィックス, [6]アイコン
```

### `$submenu` 配列の構造

```php
$submenu = [
    'index.php' => [
        [0 => 'Home', 1 => 'read', 2 => 'index.php'],
        [0 => 'Updates', 1 => 'update_core', 2 => 'update-core.php'],
    ],
    'edit.php' => [
        [0 => 'All Posts', 1 => 'edit_posts', 2 => 'edit.php'],
        [0 => 'Add New Post', 1 => 'edit_posts', 2 => 'post-new.php'],
        [0 => 'Categories', 1 => 'manage_categories', 2 => 'edit-tags.php?taxonomy=category'],
        [0 => 'Tags', 1 => 'manage_categories', 2 => 'edit-tags.php?taxonomy=post_tag'],
    ],
    // ...
];
```

### `$wp_meta_boxes` の構造

```php
$wp_meta_boxes = [
    'post' => [               // スクリーン ID
        'normal' => [          // コンテキスト（normal, side, advanced）
            'high' => [        // 優先度（high, core, default, low）
                'my-metabox' => [
                    'id'       => 'my-metabox',
                    'title'    => 'My Meta Box',
                    'callback' => 'render_my_metabox',
                    'args'     => null,
                ],
            ],
        ],
    ],
];
```

### ダッシュボードウィジェットの構造

ダッシュボードウィジェットは内部的にメタボックスとして管理されます:

```php
$wp_meta_boxes['dashboard'] = [
    'normal' => [
        'core' => [
            'dashboard_right_now'    => [...],  // 概要
            'dashboard_activity'     => [...],  // アクティビティ
        ],
    ],
    'side' => [
        'core' => [
            'dashboard_quick_press'  => [...],  // クイックドラフト
            'dashboard_primary'      => [...],  // WordPress イベントとニュース
        ],
    ],
];
```

### 管理画面のメニュー位置

| 位置 | デフォルト項目 |
|---|---|
| 2 | ダッシュボード |
| 4 | セパレーター |
| 5 | 投稿 |
| 10 | メディア |
| 15 | リンク |
| 20 | 固定ページ |
| 25 | コメント |
| 59 | セパレーター |
| 60 | 外観 |
| 65 | プラグイン |
| 70 | ユーザー |
| 75 | ツール |
| 80 | 設定 |
| 99 | セパレーター |

## 3. API リファレンス

### メニュー登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_menu_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', string $icon_url = '', int\|float $position = null): string` | トップレベルメニューを追加。フックサフィックスを返す |
| `add_submenu_page()` | `(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int\|float $position = null): string\|false` | サブメニューを追加 |
| `add_management_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「ツール」サブメニューを追加 |
| `add_options_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「設定」サブメニューを追加 |
| `add_theme_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「外観」サブメニューを追加 |
| `add_plugins_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「プラグイン」サブメニューを追加 |
| `add_users_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「ユーザー」サブメニューを追加 |
| `add_dashboard_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「ダッシュボード」サブメニューを追加 |
| `add_posts_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「投稿」サブメニューを追加 |
| `add_media_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「メディア」サブメニューを追加 |
| `add_pages_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「固定ページ」サブメニューを追加 |
| `add_comments_page()` | `(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = '', int $position = null): string\|false` | 「コメント」サブメニューを追加 |
| `remove_menu_page()` | `(string $menu_slug): array\|false` | トップレベルメニューを削除 |
| `remove_submenu_page()` | `(string $menu_slug, string $submenu_slug): array\|false` | サブメニューを削除 |

### メタボックス API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_meta_box()` | `(string $id, string $title, callable $callback, string\|array\|WP_Screen $screen = null, string $context = 'advanced', string $priority = 'default', array $callback_args = null): void` | メタボックスを追加 |
| `remove_meta_box()` | `(string $id, string\|array\|WP_Screen $screen, string $context): void` | メタボックスを削除 |
| `do_meta_boxes()` | `(string\|WP_Screen $screen, string $context, mixed $data_object): void` | メタボックスを出力 |

`$context` の値:

| 値 | 説明 |
|---|---|
| `normal` | メインカラム |
| `side` | サイドカラム |
| `advanced` | メインカラム（normal の後） |

`$priority` の値:

| 値 | 説明 |
|---|---|
| `high` | 最上部 |
| `core` | コア項目の位置 |
| `default` | デフォルト位置 |
| `low` | 最下部 |

### ダッシュボードウィジェット API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_add_dashboard_widget()` | `(string $widget_id, string $widget_name, callable $callback, callable $control_callback = null, array $callback_args = null, string $context = 'normal', string $priority = 'core'): void` | ダッシュボードウィジェットを追加 |
| `wp_dashboard_setup()` | `(): void` | コアダッシュボードウィジェットを登録（内部関数） |

### WP_Screen メソッド

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_Screen::get()` | `(string\|WP_Screen $hook_name = ''): WP_Screen` | スクリーンオブジェクトを取得（static） |
| `add_help_tab()` | `(array $args): void` | ヘルプタブを追加 |
| `remove_help_tab()` | `(string $id): void` | ヘルプタブを削除 |
| `remove_help_tabs()` | `(): void` | 全ヘルプタブを削除 |
| `set_help_sidebar()` | `(string $content): void` | ヘルプサイドバーを設定 |
| `add_option()` | `(string $option, mixed ...$args): void` | スクリーンオプションを追加 |
| `get_option()` | `(string $option, string $key = false): string` | スクリーンオプションの値を取得 |
| `get_columns()` | `(): int` | カラム数を取得 |
| `render_screen_meta()` | `(): void` | スクリーンメタ（ヘルプ・オプション）を出力 |
| `set_screen_reader_content()` | `(array $content): void` | スクリーンリーダー用テキストを設定 |
| `render_screen_reader_content()` | `(string $key): void` | スクリーンリーダー用テキストを出力 |

### 管理バー API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_action('admin_bar_menu', ...)` | — | 管理バーにメニュー項目を追加するフック |
| `WP_Admin_Bar::add_node()` | `(array $args): void` | ノードを追加 |
| `WP_Admin_Bar::remove_node()` | `(string $id): void` | ノードを削除 |
| `WP_Admin_Bar::add_group()` | `(array $args): void` | グループを追加 |
| `WP_Admin_Bar::get_node()` | `(string $id): object\|void` | ノードを取得 |
| `WP_Admin_Bar::get_nodes()` | `(): array\|void` | 全ノードを取得 |
| `show_admin_bar()` | `(bool $show): void` | 管理バーの表示/非表示を設定 |
| `is_admin_bar_showing()` | `(): bool` | 管理バーが表示されているか |

### 管理通知 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_settings_error()` | `(string $setting, string $code, string $message, string $type = 'error'): void` | 設定エラー・通知を追加 |
| `get_settings_errors()` | `(string $setting = '', bool $sanitize = false): array` | 設定エラー・通知を取得 |
| `settings_errors()` | `(string $setting = '', bool $sanitize = false, bool $hide_on_update = false): void` | 設定エラー・通知を表示 |

通知の `$type`:

| 値 | CSS クラス | 説明 |
|---|---|---|
| `error` | `notice-error` | エラー |
| `success` | `notice-success` | 成功 |
| `warning` | `notice-warning` | 警告 |
| `info` | `notice-info` | 情報 |

### 管理ページユーティリティ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_current_screen()` | `(): WP_Screen\|null` | 現在のスクリーンオブジェクトを取得 |
| `get_admin_page_title()` | `(): string` | 現在の管理ページタイトルを取得 |
| `admin_url()` | `(string $path = '', string $scheme = 'admin'): string` | 管理画面 URL を生成 |
| `add_screen_option()` | `(string $option, mixed $args = []): void` | スクリーンオプションを追加 |
| `set_screen_options()` | `(): void` | スクリーンオプションを保存 |

## 4. 実行フロー

### 管理ページロードフロー

```
wp-admin/{page}.php リクエスト
│
├── wp-admin/admin.php のロード
│   ├── wp-load.php → WordPress 初期化
│   ├── wp-admin/includes/admin.php のロード
│   │   └── 管理画面用関数の読み込み
│   │
│   ├── do_action('admin_init')
│   │
│   ├── $current_screen = WP_Screen::get($hook_suffix)
│   │
│   └── do_action("load-{$hook_suffix}")
│
├── ページ固有の処理
│
├── admin-header.php
│   ├── do_action('admin_enqueue_scripts', $hook_suffix)
│   ├── do_action('admin_print_styles')
│   ├── do_action('admin_print_scripts')
│   ├── do_action('admin_head')
│   │
│   ├── メニューのレンダリング
│   │   └── $menu, $submenu をもとに HTML 生成
│   │
│   └── do_action('in_admin_header')
│
├── ページコンテンツの出力
│   └── do_action("admin_notices") / do_action("all_admin_notices")
│
└── admin-footer.php
    ├── do_action('admin_footer', $hook_suffix)
    ├── do_action('admin_print_footer_scripts')
    └── do_action("admin_footer-{$hook_suffix}")
```

### カスタム管理ページのロードフロー

```
add_menu_page() / add_submenu_page() でページを登録
│
├── $hookname = get_plugin_page_hookname($menu_slug, $parent_slug)
│   └── 例: 'toplevel_page_my-plugin' or 'settings_page_my-plugin'
│
├── $_registered_pages[$hookname] = true
│
└── リクエスト時:
    ├── admin.php がメニュースラッグからフックサフィックスを解決
    │
    ├── do_action("load-{$hookname}")
    │   └── プラグインの初期化処理
    │
    ├── admin-header.php の出力
    │
    ├── do_action($hookname)
    │   └── add_menu_page() の $callback が呼ばれる
    │
    └── admin-footer.php の出力
```

### メタボックス出力フロー

```
do_meta_boxes($screen, 'normal', $post)
│
├── $wp_meta_boxes[$screen]['normal'] から優先度順にメタボックスを取得
│
└── 各メタボックスに対して:
    ├── $meta_box['callback'] が false なら スキップ（削除済み）
    │
    ├── <div id="{$id}" class="postbox">
    │   ├── <div class="postbox-header">
    │   │   └── <h2>{$title}</h2>
    │   │
    │   └── <div class="inside">
    │       └── call_user_func($callback, $data_object, $meta_box)
    │           └── コールバック関数がメタボックスの内容を出力
    │   </div>
    └── </div>
```

### ダッシュボードウィジェット表示フロー

```
wp-admin/index.php（ダッシュボード）
│
├── wp_dashboard_setup()
│   ├── wp_add_dashboard_widget('dashboard_right_now', ...)
│   ├── wp_add_dashboard_widget('dashboard_activity', ...)
│   ├── wp_add_dashboard_widget('dashboard_quick_press', ...)
│   ├── wp_add_dashboard_widget('dashboard_primary', ...)
│   │
│   └── do_action('wp_dashboard_setup')
│       └── プラグインが wp_add_dashboard_widget() でウィジェットを追加
│
├── wp_dashboard()
│   ├── $columns = get_current_screen()->get_columns()
│   │
│   ├── <div id="dashboard-widgets">
│   │   ├── do_meta_boxes('dashboard', 'normal', '')
│   │   ├── do_meta_boxes('dashboard', 'side', '')
│   │   └── do_meta_boxes('dashboard', 'column3', '')
│   └── </div>
│
└── ユーザーのウィジェット配置はユーザーメタ 'meta-box-order_dashboard' に保存
```

## 5. WP_List_Table

管理画面の一覧表示を標準化するクラスです。

```php
abstract class WP_List_Table {
    public $items;           // 表示するアイテムの配列
    protected $screen;       // WP_Screen
    protected $_actions;     // バルクアクション
    protected $_pagination_args = [];

    // 主要なオーバーライド対象メソッド
    abstract public function prepare_items(): void;
    abstract public function get_columns(): array;
    public function get_sortable_columns(): array;
    public function column_default($item, $column_name): string|void;
    public function column_cb($item): void;
    public function get_bulk_actions(): array;
    public function extra_tablenav(string $which): void;

    // レンダリングメソッド
    public function display(): void;
    public function display_rows(): void;
    public function single_row($item): void;
    public function pagination(string $which): void;
    public function search_box(string $text, string $input_id): void;
    public function views(): array;
}
```

## 6. フック一覧

### Action フック

| フック名 | 発火タイミング | パラメータ |
|---|---|---|
| `admin_init` | 管理画面の初期化時 | なし |
| `admin_menu` | 管理メニュー登録時 | なし |
| `admin_bar_menu` | 管理バーメニュー登録時 | `$wp_admin_bar` |
| `wp_dashboard_setup` | ダッシュボードウィジェット登録時 | なし |
| `admin_enqueue_scripts` | 管理画面のスクリプト/スタイル読み込み時 | `$hook_suffix` |
| `admin_print_styles` | 管理画面の `<head>` 内（スタイル出力） | なし |
| `admin_print_scripts` | 管理画面の `<head>` 内（スクリプト出力） | なし |
| `admin_head` | 管理画面の `<head>` 末尾 | なし |
| `admin_head-{$hook_suffix}` | 特定ページの `<head>` 末尾 | なし |
| `admin_notices` | 管理画面の通知領域 | なし |
| `all_admin_notices` | 全管理画面の通知領域 | なし |
| `in_admin_header` | 管理ヘッダー内 | なし |
| `admin_footer` | 管理画面のフッター | `$hook_suffix` |
| `admin_footer-{$hook_suffix}` | 特定ページのフッター | なし |
| `admin_print_footer_scripts` | フッターのスクリプト出力 | なし |
| `load-{$hook_suffix}` | 管理ページのロード時 | なし |
| `add_meta_boxes` | メタボックス登録時 | `$post_type`, `$post` |
| `add_meta_boxes_{$post_type}` | 特定投稿タイプのメタボックス登録時 | `$post` |
| `save_post` | 投稿保存時（メタボックスのデータ保存に使用） | `$post_id`, `$post`, `$update` |
| `do_meta_boxes` | メタボックス出力完了後 | `$screen`, `$context`, `$data_object` |

### Filter フック

| フック名 | フィルター対象 | パラメータ |
|---|---|---|
| `admin_title` | 管理画面の `<title>` | `$admin_title`, `$title` |
| `admin_body_class` | `<body>` の CSS クラス | `$classes` |
| `admin_footer_text` | フッターのテキスト | `$text` |
| `update_footer` | フッターの更新テキスト | `$content` |
| `custom_menu_order` | メニュー順序のカスタマイズを有効化 | `$custom` |
| `menu_order` | メニューの並び順 | `$menu_order` |
| `parent_file` | 現在の親ファイル | `$parent_file` |
| `submenu_file` | 現在のサブメニューファイル | `$submenu_file`, `$parent_file` |
| `set-screen-option` | スクリーンオプションの保存値 | `$screen_option`, `$option`, `$value` |
| `screen_settings` | スクリーン設定の HTML | `$settings`, `$screen` |
| `screen_options_show_screen` | スクリーンオプション表示フラグ | `$show_screen`, `$screen` |
| `manage_posts_columns` | 投稿一覧のカラム | `$columns`, `$post_type` |
| `manage_{$post_type}_posts_columns` | 特定投稿タイプのカラム | `$columns` |
| `manage_posts_custom_column` | カスタムカラムの出力 | `$column_name`, `$post_id` |
| `post_row_actions` | 投稿行のアクションリンク | `$actions`, `$post` |
| `page_row_actions` | 固定ページ行のアクションリンク | `$actions`, `$post` |
| `bulk_actions-{$screen}` | バルクアクション | `$actions` |
| `handle_bulk_actions-{$screen}` | バルクアクション処理 | `$redirect_url`, `$action`, `$post_ids` |
| `admin_bar_menu` | 管理バーメニュー | `$wp_admin_bar` |
| `show_admin_bar` | 管理バーの表示フラグ | `$show` |
| `wp_dashboard_widgets` | ダッシュボードウィジェットの一覧 | `$widgets` |
