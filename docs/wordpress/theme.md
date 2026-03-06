# WordPress テーマ / Enqueue API 仕様

## 1. 概要

WordPress のテーマ API は、テーマの登録、テーマ機能の宣言、テンプレートシステム、およびスクリプト/スタイルシートの管理（Enqueue API）を提供します。Enqueue API は `WP_Scripts` / `WP_Styles` クラスによってアセットの依存関係解決とロード順序を管理します。

### 主要コンポーネント

| コンポーネント | クラス / ファイル | 説明 |
|---|---|---|
| `WP_Theme` | `class-wp-theme.php` | テーマの情報と設定を管理 |
| `WP_Scripts` | `class-wp-scripts.php` | JavaScript の登録・依存解決・出力管理 |
| `WP_Styles` | `class-wp-styles.php` | CSS の登録・依存解決・出力管理 |
| `WP_Dependencies` | `class-wp-dependencies.php` | Scripts / Styles の共通基底クラス |
| `WP_Dependency` | `class-wp-dependency.php` | 個々のアセットを表すデータクラス |
| テーマサポート | `add_theme_support()` | テーマの機能宣言 |
| テーマ Mod | `theme_mods_{$theme}` オプション | テーマ固有の設定値 |
| テンプレートシステム | テンプレート階層 | ページ種別に応じたテンプレートファイル選択 |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_scripts` | `WP_Scripts` | スクリプト管理インスタンス |
| `$wp_styles` | `WP_Styles` | スタイル管理インスタンス |
| `$_wp_theme_features` | `array` | テーマサポート機能の登録状態 |
| `$wp_theme_directories` | `string[]` | テーマディレクトリの一覧 |

## 2. データ構造

### WP_Theme クラス

```php
class WP_Theme implements ArrayAccess {
    // テーマヘッダー情報
    private $headers = [
        'Name'        => 'Theme Name',
        'ThemeURI'    => 'Theme URI',
        'Description' => 'Description',
        'Author'      => 'Author',
        'AuthorURI'   => 'Author URI',
        'Version'     => 'Version',
        'Template'    => 'Template',       // 親テーマのディレクトリ名（子テーマの場合）
        'Status'      => 'Status',
        'Tags'        => 'Tags',
        'TextDomain'  => 'Text Domain',
        'DomainPath'  => 'Domain Path',
        'RequiresWP'  => 'Requires at least',
        'RequiresPHP' => 'Requires PHP',
        'UpdateURI'   => 'Update URI',
    ];

    private $stylesheet;      // テーマスラッグ（ディレクトリ名）
    private $template;        // 親テーマのスラッグ（子テーマの場合）
    private $parent;          // WP_Theme（親テーマオブジェクト）
    private $theme_root;      // テーマルートディレクトリ
    private $theme_root_uri;  // テーマルート URL
}
```

### WP_Dependencies クラス（Scripts / Styles の基底）

```php
class WP_Dependencies {
    public $registered = [];   // ハンドル => WP_Dependency の連想配列
    public $queue      = [];   // エンキューされたハンドルの配列
    public $to_do      = [];   // 出力予定のハンドルの配列（依存解決後）
    public $done       = [];   // 出力済みのハンドルの配列
    public $args       = [];   // ハンドル => 引数
    public $groups     = [];   // ハンドル => グループ番号
    public $group      = 0;    // 現在のグループ番号
}
```

### WP_Dependency クラス

```php
class WP_Dependency {
    public $handle;   // ハンドル名（一意の識別子）
    public $src;      // ソース URL
    public $deps;     // 依存ハンドルの配列
    public $ver;      // バージョン文字列
    public $args;     // 追加引数（scripts: $in_footer, styles: $media）
    public $extra;    // 追加データ（条件付きスタイル、インラインスクリプト等）

    public function add_data(string $name, mixed $data): bool;
}
```

### WP_Scripts クラス

```php
class WP_Scripts extends WP_Dependencies {
    public $base_url;           // ベース URL
    public $content_url;        // コンテンツ URL
    public $default_version;    // デフォルトバージョン
    public $in_footer    = [];  // フッター出力するスクリプトのハンドル配列
    public $concat       = '';  // 結合対象のハンドルリスト
    public $concat_version = '';// 結合バージョン
    public $do_concat    = false; // 結合モードフラグ
    public $print_html   = '';  // 結合スクリプトの HTML
    public $print_code   = '';  // インラインスクリプトの HTML
}
```

### WP_Styles クラス

```php
class WP_Styles extends WP_Dependencies {
    public $base_url;           // ベース URL
    public $content_url;        // コンテンツ URL
    public $default_version;    // デフォルトバージョン
    public $text_direction = 'ltr'; // テキスト方向
    public $concat       = '';  // 結合対象のハンドルリスト
    public $concat_version = '';// 結合バージョン
    public $do_concat    = false; // 結合モードフラグ
    public $print_html   = '';  // 結合スタイルの HTML
    public $print_code   = '';  // インラインスタイルの HTML
    public $default_dirs;       // デフォルトディレクトリ
}
```

### `$extra` データのキー

#### スクリプト用

| キー | 説明 |
|---|---|
| `data` | `wp_localize_script()` で追加されたデータ |
| `before` | スクリプトタグの前に出力するインラインスクリプト配列 |
| `after` | スクリプトタグの後に出力するインラインスクリプト配列 |
| `group` | グループ番号（`1` = フッター） |
| `strategy` | ロード戦略（`defer` / `async`） |
| `conditional` | 条件コメント（IE 用） |

#### スタイル用

| キー | 説明 |
|---|---|
| `rtl` | RTL スタイルシートの URL（または `'replace'`） |
| `suffix` | ファイル名サフィックス |
| `alt` | 代替スタイルシートフラグ |
| `title` | スタイルシートのタイトル |
| `conditional` | 条件コメント（IE 用） |
| `path` | ファイルシステムパス（インライン化用） |
| `after` | インラインスタイルの配列 |

### テーマ Mod

テーマ固有の設定は `theme_mods_{$stylesheet}` オプションに保存されます:

```php
// get_theme_mods() の例
[
    'custom_logo'       => 42,                  // カスタムロゴのアタッチメント ID
    'header_image'      => 'https://...',       // ヘッダー画像 URL
    'background_color'  => 'ffffff',            // 背景色
    'nav_menu_locations' => ['primary' => 5],   // メニュー位置の割り当て
    'sidebars_widgets'  => ['data' => [...]],   // ウィジェット配置
    'custom_css_post_id' => 123,                // カスタム CSS の投稿 ID
]
```

## 3. API リファレンス

### スクリプト API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_register_script()` | `(string $handle, string\|false $src, string[] $deps = [], string\|bool\|null $ver = false, array\|bool $args = false): bool` | スクリプトを登録 |
| `wp_deregister_script()` | `(string $handle): void` | スクリプトの登録を解除 |
| `wp_enqueue_script()` | `(string $handle, string $src = '', string[] $deps = [], string\|bool\|null $ver = false, array\|bool $args = false): void` | スクリプトをエンキュー（登録 + キューに追加） |
| `wp_dequeue_script()` | `(string $handle): void` | スクリプトをデキュー |
| `wp_script_is()` | `(string $handle, string $status = 'enqueued'): bool` | スクリプトの状態を確認 |
| `wp_localize_script()` | `(string $handle, string $object_name, array $l10n): bool` | スクリプトにデータオブジェクトを渡す |
| `wp_add_inline_script()` | `(string $handle, string $data, string $position = 'after'): bool` | インラインスクリプトを追加 |
| `wp_set_script_translations()` | `(string $handle, string $domain = 'default', string $path = ''): bool` | スクリプトの翻訳を設定 |
| `wp_script_add_data()` | `(string $handle, string $key, mixed $value): bool` | スクリプトにデータを追加 |

`wp_register_script()` / `wp_enqueue_script()` の `$args` パラメータ（WordPress 6.3+）:

```php
$args = [
    'in_footer' => true,       // フッターに出力するか（デフォルト: false）
    'strategy'  => 'defer',    // ロード戦略: 'defer' | 'async'（デフォルト: ''）
];
// 後方互換: $args が bool の場合は $in_footer として扱われる
```

### スタイル API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_register_style()` | `(string $handle, string\|false $src, string[] $deps = [], string\|bool\|null $ver = false, string $media = 'all'): bool` | スタイルを登録 |
| `wp_deregister_style()` | `(string $handle): void` | スタイルの登録を解除 |
| `wp_enqueue_style()` | `(string $handle, string $src = '', string[] $deps = [], string\|bool\|null $ver = false, string $media = 'all'): void` | スタイルをエンキュー |
| `wp_dequeue_style()` | `(string $handle): void` | スタイルをデキュー |
| `wp_style_is()` | `(string $handle, string $status = 'enqueued'): bool` | スタイルの状態を確認 |
| `wp_add_inline_style()` | `(string $handle, string $data): bool` | インラインスタイルを追加 |
| `wp_style_add_data()` | `(string $handle, string $key, mixed $value): bool` | スタイルにデータを追加 |

`$status` の値:

| 値 | 説明 |
|---|---|
| `registered` | 登録されている（`$wp_scripts->registered` に存在） |
| `enqueued` / `queue` | キューに入っている |
| `done` | 出力済み |
| `to_do` | 出力待ち |

### テーマ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_get_theme()` | `(string $stylesheet = null, string $theme_root = null): WP_Theme` | テーマオブジェクトを取得 |
| `wp_get_themes()` | `(array $args = []): WP_Theme[]` | インストール済みテーマ一覧を取得 |
| `get_stylesheet()` | `(): string` | 現在のテーマスラッグを取得 |
| `get_template()` | `(): string` | 親テーマスラッグを取得（子テーマの場合は親テーマ） |
| `get_stylesheet_directory()` | `(): string` | テーマディレクトリパスを取得 |
| `get_stylesheet_directory_uri()` | `(): string` | テーマディレクトリ URL を取得 |
| `get_template_directory()` | `(): string` | 親テーマディレクトリパスを取得 |
| `get_template_directory_uri()` | `(): string` | 親テーマディレクトリ URL を取得 |
| `get_stylesheet_uri()` | `(): string` | `style.css` の URL を取得 |
| `switch_theme()` | `(string $stylesheet): void` | テーマを切り替え |
| `get_theme_file_path()` | `(string $file = ''): string` | テーマファイルのパスを取得（子テーマ優先） |
| `get_theme_file_uri()` | `(string $file = ''): string` | テーマファイルの URL を取得（子テーマ優先） |

### テーマサポート API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_theme_support()` | `(string $feature, mixed ...$args): void` | テーマの機能サポートを宣言 |
| `remove_theme_support()` | `(string $feature): bool` | テーマサポートを削除 |
| `current_theme_supports()` | `(string $feature, mixed ...$args): bool` | テーマが機能をサポートしているか |
| `get_theme_support()` | `(string $feature): mixed` | テーマサポートの引数を取得 |

主要なテーマサポート機能:

| 機能 | 説明 |
|---|---|
| `title-tag` | `<title>` タグの自動管理 |
| `post-thumbnails` | アイキャッチ画像 |
| `post-formats` | 投稿フォーマット |
| `custom-logo` | カスタムロゴ |
| `custom-header` | カスタムヘッダー |
| `custom-background` | カスタム背景 |
| `automatic-feed-links` | RSS フィードリンクの自動出力 |
| `html5` | HTML5 マークアップのサポート |
| `menus` | ナビゲーションメニュー |
| `widgets` | ウィジェット |
| `editor-styles` | エディタスタイル |
| `wp-block-styles` | ブロックスタイル |
| `responsive-embeds` | レスポンシブ埋め込み |
| `align-wide` | ワイド/全幅ブロック配置 |
| `editor-color-palette` | エディタのカラーパレット |
| `editor-font-sizes` | エディタのフォントサイズ |

### テーマ Mod API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_theme_mod()` | `(string $name, mixed $default = false): mixed` | テーマ設定値を取得 |
| `set_theme_mod()` | `(string $name, mixed $value): void` | テーマ設定値を保存 |
| `remove_theme_mod()` | `(string $name): void` | テーマ設定値を削除 |
| `get_theme_mods()` | `(): array` | 全テーマ設定値を取得 |
| `remove_theme_mods()` | `(): void` | 全テーマ設定値を削除 |

### テンプレート API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_header()` | `(string\|null $name = null, array $args = []): void\|false` | ヘッダーテンプレートを読み込み |
| `get_footer()` | `(string\|null $name = null, array $args = []): void\|false` | フッターテンプレートを読み込み |
| `get_sidebar()` | `(string\|null $name = null, array $args = []): void\|false` | サイドバーテンプレートを読み込み |
| `get_template_part()` | `(string $slug, string\|null $name = null, array $args = []): void\|false` | テンプレートパーツを読み込み |
| `get_search_form()` | `(array $args = []): void\|string` | 検索フォームを取得 |
| `locate_template()` | `(string\|string[] $template_names, bool $load = false, bool $load_once = true, array $args = []): string` | テンプレートファイルを検索 |
| `load_template()` | `(string $_template_file, bool $load_once = true, array $args = []): void` | テンプレートファイルを読み込み |

### Enqueue フック

| 関数 | 使用場所 | 説明 |
|---|---|---|
| `wp_enqueue_scripts` | フロントエンドのスクリプト/スタイル | テーマやプラグインがアセットをエンキューする標準フック |
| `admin_enqueue_scripts` | 管理画面のスクリプト/スタイル | 管理画面用アセットのエンキュー |
| `login_enqueue_scripts` | ログイン画面のスクリプト/スタイル | ログイン画面用アセットのエンキュー |

## 4. 実行フロー

### スクリプト/スタイルの出力フロー

```
wp_head() / wp_footer()
│
├── wp_head():
│   ├── wp_enqueue_scripts アクション
│   │   └── テーマ/プラグインが wp_enqueue_script() / wp_enqueue_style() を呼ぶ
│   │
│   ├── wp_print_styles()
│   │   └── WP_Styles::do_items()
│   │       ├── 依存関係の解決
│   │       │   └── WP_Dependencies::all_deps($handles)
│   │       │       ├── 各ハンドルの $deps を再帰的に解決
│   │       │       ├── 循環依存を検出
│   │       │       └── $to_do 配列にソート済みで格納
│   │       │
│   │       └── 各ハンドルを出力
│   │           ├── do_item($handle)
│   │           │   ├── $src が false: 依存のみ（バーチャルハンドル）
│   │           │   ├── 条件コメント（$extra['conditional']）
│   │           │   ├── <link rel="stylesheet" href="{$src}?ver={$ver}" media="{$media}">
│   │           │   ├── RTL スタイル（$extra['rtl']）
│   │           │   └── インラインスタイル（$extra['after']）
│   │           └── $done[] に追加
│   │
│   └── wp_print_head_scripts()
│       └── WP_Scripts::do_items()（ヘッダー用スクリプトのみ）
│           └── 各ハンドルを出力
│               ├── do_item($handle)
│               │   ├── $extra['data']（localize データ）を出力
│               │   ├── $extra['before'] インラインスクリプト
│               │   ├── <script src="{$src}?ver={$ver}" {$strategy}></script>
│               │   │   └── strategy: defer / async / なし
│               │   └── $extra['after'] インラインスクリプト
│               └── $done[] に追加
│
└── wp_footer():
    └── wp_print_footer_scripts()
        └── WP_Scripts::do_items()（フッター用スクリプト）
            └── $in_footer のハンドルを出力
```

### スクリプトの `strategy` による読み込み制御

```
strategy なし（デフォルト）:
    <script src="..."></script>
    → ブラウザの解析を停止して即座に実行

strategy = 'defer':
    <script src="..." defer></script>
    → HTML パース完了後に実行（DOMContentLoaded 前）
    → 依存関係のあるスクリプトも自動的に defer になる

strategy = 'async':
    <script src="..." async></script>
    → ダウンロード完了次第実行（順序保証なし）
    → 依存スクリプトがある場合は defer にフォールバック
```

### テンプレート解決フロー

```
URL リクエスト → WP::main()
│
├── WP_Query で投稿データを取得
│
├── template_redirect アクション
│
├── template-loader.php
│   │
│   ├── テンプレートタイプの判定（is_single, is_page, is_archive 等）
│   │
│   ├── テンプレート階層に基づいてファイルを検索
│   │   └── 例: 単一投稿の場合
│   │       1. single-{post_type}-{slug}.php
│   │       2. single-{post_type}.php
│   │       3. single.php
│   │       4. singular.php
│   │       5. index.php
│   │
│   ├── apply_filters('{$type}_template', $template, $type, $templates)
│   │
│   ├── apply_filters('template_include', $template)
│   │
│   └── include $template
│       ├── get_header()
│       ├── コンテンツの出力
│       ├── get_sidebar()
│       └── get_footer()
│
└── 出力完了
```

### テーマ切り替えフロー

```
switch_theme($stylesheet)
│
├── 旧テーマの情報を取得
│
├── update_option('stylesheet', $stylesheet)
├── update_option('template', $template)
│
├── wp_clean_themes_cache()
│
├── do_action('switch_theme', $new_theme_name, $new_theme, $old_theme)
│
└── テーマ関連オプションの更新
    ├── current_theme_supports() のリセット
    ├── sidebars_widgets の更新
    └── delete_option('theme_mods_' . $old_stylesheet)（旧テーマ mod は保持される場合あり）
```

## 5. テンプレート階層

WordPress のテンプレート階層は、表示するページの種類に応じてテンプレートファイルを選択する仕組みです。

| ページ種別 | テンプレート優先順位 |
|---|---|
| フロントページ | `front-page.php` → `home.php` → `index.php` |
| ブログホーム | `home.php` → `index.php` |
| 単一投稿 | `single-{post_type}-{slug}.php` → `single-{post_type}.php` → `single.php` → `singular.php` → `index.php` |
| 固定ページ | `{custom_template}.php` → `page-{slug}.php` → `page-{id}.php` → `page.php` → `singular.php` → `index.php` |
| カテゴリ | `category-{slug}.php` → `category-{id}.php` → `category.php` → `archive.php` → `index.php` |
| タグ | `tag-{slug}.php` → `tag-{id}.php` → `tag.php` → `archive.php` → `index.php` |
| 著者 | `author-{nicename}.php` → `author-{id}.php` → `author.php` → `archive.php` → `index.php` |
| 日付 | `date.php` → `archive.php` → `index.php` |
| カスタム投稿タイプアーカイブ | `archive-{post_type}.php` → `archive.php` → `index.php` |
| カスタムタクソノミー | `taxonomy-{taxonomy}-{term}.php` → `taxonomy-{taxonomy}.php` → `taxonomy.php` → `archive.php` → `index.php` |
| 検索 | `search.php` → `index.php` |
| 404 | `404.php` → `index.php` |
| アタッチメント | `{mimetype}-{subtype}.php` → `{subtype}.php` → `{mimetype}.php` → `attachment.php` → `single-attachment-{slug}.php` → `single.php` → `index.php` |

## 6. フック一覧

### Action フック

| フック名 | 発火タイミング | パラメータ |
|---|---|---|
| `after_setup_theme` | テーマの `functions.php` 読み込み後 | なし |
| `wp_enqueue_scripts` | フロントエンドのアセット読み込みタイミング | なし |
| `admin_enqueue_scripts` | 管理画面のアセット読み込みタイミング | `$hook_suffix` |
| `login_enqueue_scripts` | ログイン画面のアセット読み込みタイミング | なし |
| `wp_head` | `<head>` 内の出力 | なし |
| `wp_footer` | `</body>` 前の出力 | なし |
| `wp_print_styles` | スタイル出力時 | なし |
| `wp_print_scripts` | スクリプト出力時 | なし |
| `wp_print_footer_scripts` | フッタースクリプト出力時 | なし |
| `wp_body_open` | `<body>` タグ直後 | なし |
| `switch_theme` | テーマ切り替え時 | `$new_name`, `$new_theme`, `$old_theme` |
| `after_switch_theme` | テーマ切り替え後（最初のリクエスト時） | なし |
| `get_header` | `get_header()` 呼び出し時 | `$name`, `$args` |
| `get_footer` | `get_footer()` 呼び出し時 | `$name`, `$args` |
| `get_sidebar` | `get_sidebar()` 呼び出し時 | `$name`, `$args` |
| `get_template_part` | `get_template_part()` 呼び出し時 | `$slug`, `$name`, `$templates`, `$args` |
| `template_redirect` | テンプレートのロード前 | なし |

### Filter フック

| フック名 | フィルター対象 | パラメータ |
|---|---|---|
| `stylesheet` | 現在のスタイルシート名 | `$stylesheet` |
| `template` | 現在のテンプレート名 | `$template` |
| `template_include` | ロードするテンプレートファイル | `$template` |
| `{$type}_template` | テンプレートタイプ別のファイル | `$template`, `$type`, `$templates` |
| `{$type}_template_hierarchy` | テンプレート候補ファイルの一覧 | `$templates` |
| `theme_mod_{$name}` | テーマ Mod 値 | `$current_mod` |
| `theme_file_path` | テーマファイルパス | `$path`, `$file` |
| `theme_file_uri` | テーマファイル URL | `$url`, `$file` |
| `current_theme_supports-{$feature}` | テーマサポートの判定 | `$supports`, `$args`, `$feature` |
| `script_loader_tag` | スクリプトの HTML タグ | `$tag`, `$handle`, `$src` |
| `style_loader_tag` | スタイルの HTML タグ | `$tag`, `$handle`, `$href`, `$media` |
| `script_loader_src` | スクリプトの URL | `$src`, `$handle` |
| `style_loader_src` | スタイルの URL | `$src`, `$handle` |
| `wp_default_scripts` | デフォルトスクリプトの登録 | `$wp_scripts` |
| `wp_default_styles` | デフォルトスタイルの登録 | `$wp_styles` |
| `print_scripts_array` | 出力するスクリプトハンドルの配列 | `$to_do` |
| `print_styles_array` | 出力するスタイルハンドルの配列 | `$to_do` |
| `wp_inline_script_attributes` | インラインスクリプト属性 | `$attributes`, `$javascript` |
| `wp_script_attributes` | スクリプト要素の属性 | `$attributes` |
