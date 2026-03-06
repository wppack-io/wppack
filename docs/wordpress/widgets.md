# WordPress ウィジェット API 仕様

## 1. 概要

WordPress のウィジェット API は、サイドバーやその他のウィジェットエリアにコンテンツブロックを追加するための仕組みです。ウィジェットは `WP_Widget` クラスを継承して作成し、`register_widget()` で登録します。ウィジェットエリア（サイドバー）は `register_sidebar()` で定義します。

ウィジェットシステムは以下のグローバル変数で状態を管理します:

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_widget_factory` | `WP_Widget_Factory` | ウィジェットクラスのファクトリ。登録された全ウィジェットを保持 |
| `$wp_registered_widgets` | `array` | 登録された全ウィジェットインスタンスの配列 |
| `$wp_registered_widget_controls` | `array` | ウィジェットコントロール（設定フォーム）の配列 |
| `$wp_registered_widget_updates` | `array` | ウィジェット更新コールバックの配列 |
| `$wp_registered_sidebars` | `array` | 登録された全サイドバー（ウィジェットエリア）の配列 |
| `$_wp_sidebars_widgets` | `array` | サイドバーとウィジェットの対応関係 |

## 2. データ構造

### WP_Widget クラス

全ウィジェットの基底クラスです。ウィジェットの表示・設定・更新ロジックをカプセル化します。

```php
class WP_Widget {
    public $id_base;          // ウィジェットの基底ID（例: 'text', 'categories'）
    public $name;             // ウィジェットの表示名
    public $widget_options;   // ウィジェットオプション（classname, description 等）
    public $control_options;  // コントロールオプション（width, height 等）
    public $number = false;   // マルチウィジェットのインスタンス番号
    public $id = false;       // ウィジェットの完全ID（id_base-number）
    public $updated = false;  // 更新済みフラグ
    public $option_name;      // オプション保存名（widget_{id_base}）
}
```

### WP_Widget_Factory クラス

ウィジェットクラスの登録を管理するファクトリです。

```php
class WP_Widget_Factory {
    public $widgets = [];  // クラス名 => WP_Widget インスタンス
}
```

### `$wp_registered_sidebars` の構造

```
$wp_registered_sidebars = [
    'sidebar-1' => [
        'name'          => 'Main Sidebar',
        'id'            => 'sidebar-1',
        'description'   => '',
        'class'         => '',
        'before_widget' => '<li id="%1$s" class="widget %2$s">',
        'after_widget'  => '</li>',
        'before_title'  => '<h2 class="widgettitle">',
        'after_title'   => '</h2>',
        'show_in_rest'  => false,
    ],
    ...
];
```

### `$wp_registered_widgets` の構造

```
$wp_registered_widgets = [
    'text-2' => [
        'name'        => 'Text',
        'id'          => 'text-2',
        'callback'    => [$widget_instance, 'display_callback'],
        'params'      => [['number' => 2]],
        'classname'   => 'widget_text',
        'description' => 'Arbitrary text.',
    ],
    ...
];
```

### `$_wp_sidebars_widgets` の構造

```
$_wp_sidebars_widgets = [
    'sidebar-1'        => ['text-2', 'categories-3', 'search-4'],
    'sidebar-2'        => ['archives-2'],
    'wp_inactive_widgets' => ['calendar-1'],
];
```

インアクティブウィジェットは `wp_inactive_widgets` キーに格納されます。

## 3. API リファレンス

### ウィジェット登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_widget()` | `(string\|WP_Widget $widget): void` | ウィジェットクラスを登録 |
| `unregister_widget()` | `(string\|WP_Widget $widget): void` | ウィジェットクラスを登録解除 |
| `wp_register_widget_control()` | `(int\|string $id, string $name, callable $control_callback, array $options = [], mixed ...$params): void` | ウィジェットコントロールを登録 |
| `wp_unregister_widget_control()` | `(int\|string $id): void` | ウィジェットコントロールを解除 |
| `wp_register_sidebar_widget()` | `(int\|string $id, string $name, callable $output_callback, array $options = [], mixed ...$params): void` | サイドバーウィジェットを直接登録 |
| `wp_unregister_sidebar_widget()` | `(int\|string $id): void` | サイドバーウィジェットを解除 |

### サイドバー登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_sidebar()` | `(array\|string $args = []): string` | サイドバー（ウィジェットエリア）を登録。ID を返す |
| `unregister_sidebar()` | `(string\|int $sidebar_id): void` | サイドバーを登録解除 |
| `register_sidebars()` | `(int $number = 1, array\|string $args = []): void` | 複数のサイドバーを一括登録 |

### 表示 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `dynamic_sidebar()` | `(int\|string $index = 1): bool` | サイドバーのウィジェットを表示。ウィジェットがあれば `true` |
| `the_widget()` | `(string $widget, array $instance = [], array $args = []): void` | ウィジェットをサイドバー外で直接表示 |
| `wp_render_widget()` | `(string $widget_id, string $sidebar_id): string` | ウィジェットの出力を文字列で取得（WordPress 5.8+） |

### クエリ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `is_active_widget()` | `(callable\|false $callback = false, string\|false $widget_id = false, string\|false $id_base = false, bool $skip_inactive = true): string\|false` | ウィジェットがアクティブか確認 |
| `is_active_sidebar()` | `(string\|int $index): bool` | サイドバーにウィジェットがあるか確認 |
| `is_registered_sidebar()` | `(string\|int $sidebar_id): bool` | サイドバーが登録されているか確認（WordPress 5.9+） |
| `wp_get_sidebars_widgets()` | `(): array` | 全サイドバーのウィジェット割り当てを取得 |
| `wp_set_sidebars_widgets()` | `(array $sidebars_widgets): void` | サイドバーのウィジェット割り当てを設定 |
| `wp_get_widget_defaults()` | `(): array` | デフォルトのウィジェット配置を取得 |

### WP_Widget の抽象メソッド

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `widget()` | `(array $args, array $instance): void` | フロントエンドのウィジェット出力（必須） |
| `form()` | `(array $instance): string` | 管理画面の設定フォーム出力 |
| `update()` | `(array $new_instance, array $old_instance): array` | 設定値の検証と保存処理 |

### WP_Widget の主要メソッド

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `__construct()` | `(string $id_base, string $name, array $widget_options = [], array $control_options = [])` | コンストラクタ |
| `get_settings()` | `(): array` | 全インスタンスの設定を取得 |
| `save_settings()` | `(array $settings): void` | 全インスタンスの設定を保存 |
| `get_field_name()` | `(string $field_name): string` | フォームフィールドの name 属性を生成 |
| `get_field_id()` | `(string $field_name): string` | フォームフィールドの id 属性を生成 |
| `display_callback()` | `(array $args, int $widget_args = 1): void` | 表示コールバック（内部で `widget()` を呼ぶ） |
| `update_callback()` | `(int $deprecated = 0): void` | 更新コールバック（内部で `update()` を呼ぶ） |
| `form_callback()` | `(int $widget_args = 1): string\|false` | フォームコールバック（内部で `form()` を呼ぶ） |

## 4. 実行フロー

### ウィジェット登録フロー

```
widgets_init アクション発火
│
├── テーマ/プラグインが register_widget('My_Widget') を呼ぶ
│   │
│   ├── WP_Widget_Factory::register($widget)
│   │   ├── $widget が文字列（クラス名）の場合: new $widget() でインスタンス化
│   │   └── $this->widgets[$widget->id_base] = $widget
│   │
│   └── WP_Widget::_register()
│       ├── マルチウィジェット: 保存済みインスタンスごとに設定
│       │   ├── $this->_set($number)
│       │   └── wp_register_sidebar_widget() で各インスタンスを登録
│       │       → $wp_registered_widgets に追加
│       │
│       └── wp_register_widget_control() でコントロールを登録
│           → $wp_registered_widget_controls に追加
│
└── テーマが register_sidebar($args) を呼ぶ
    └── $wp_registered_sidebars[$id] = $args
```

### ウィジェット表示フロー

```
dynamic_sidebar('sidebar-1')
│
├── $sidebars_widgets = wp_get_sidebars_widgets()
│   └── 'sidebar-1' => ['text-2', 'categories-3']
│
├── do_action('dynamic_sidebar_before', $index, true)
│
├── foreach ($sidebars_widgets['sidebar-1'] as $id):
│   │
│   ├── $wp_registered_widgets[$id] が未登録ならスキップ
│   │
│   ├── $params = apply_filters('dynamic_sidebar_params', $params)
│   │
│   ├── do_action('dynamic_sidebar', $wp_registered_widgets[$id])
│   │
│   └── call_user_func_array($callback, $params)
│       → WP_Widget::display_callback()
│           ├── $instance = $this->get_settings()[$this->number]
│           ├── $instance = apply_filters('widget_display_callback', $instance, $this, $args)
│           └── $this->widget($args, $instance)
│
└── do_action('dynamic_sidebar_after', $index, true)
```

### ウィジェット設定更新フロー

```
POST /wp-admin/admin-ajax.php (action=save-widget)
│
├── wp_ajax_save_widget()
│   │
│   ├── check_ajax_referer('save-sidebar-widgets')
│   │
│   ├── $id_base, $number を POST データから取得
│   │
│   └── WP_Widget::update_callback()
│       ├── $old_instance = $this->get_settings()[$this->number]
│       ├── $new_instance = $this->update($new_instance, $old_instance)
│       │
│       ├── $instance = apply_filters(
│       │       'widget_update_callback',
│       │       $new_instance, $new_instance, $old_instance, $this
│       │   )
│       │
│       ├── $all_instances[$this->number] = $instance
│       └── $this->save_settings($all_instances)
│           └── update_option("widget_{$id_base}", $all_instances)
```

## 5. ウィジェットデータの保存形式

ウィジェットの設定は `wp_options` テーブルに保存されます:

| オプション名 | 値の形式 | 説明 |
|---|---|---|
| `widget_{id_base}` | `array` | 各ウィジェットクラスのインスタンス設定 |
| `sidebars_widgets` | `array` | サイドバーとウィジェットの対応関係 |

```php
// widget_text のオプション値の例
[
    2 => ['title' => 'About', 'text' => 'Hello world', 'filter' => true],
    3 => ['title' => 'Notice', 'text' => 'Important info', 'filter' => true],
    '_multiwidget' => 1,  // マルチウィジェットフラグ
]
```

`_multiwidget` キーは、ウィジェットが複数インスタンスをサポートすることを示します。インスタンス番号は `2` から始まります（`1` は使用されません）。

## 6. コアウィジェット一覧

| クラス名 | ID Base | 説明 |
|---|---|---|
| `WP_Widget_Pages` | `pages` | ページ一覧 |
| `WP_Widget_Calendar` | `calendar` | カレンダー |
| `WP_Widget_Archives` | `archives` | アーカイブ一覧 |
| `WP_Widget_Media_Audio` | `media_audio` | 音声プレーヤー |
| `WP_Widget_Media_Image` | `media_image` | 画像表示 |
| `WP_Widget_Media_Video` | `media_video` | 動画プレーヤー |
| `WP_Widget_Media_Gallery` | `media_gallery` | ギャラリー |
| `WP_Widget_Meta` | `meta` | メタ情報（ログイン/RSS リンク等） |
| `WP_Widget_Search` | `search` | 検索フォーム |
| `WP_Widget_Text` | `text` | テキスト/HTML |
| `WP_Widget_Categories` | `categories` | カテゴリ一覧 |
| `WP_Widget_Recent_Posts` | `recent-posts` | 最近の投稿 |
| `WP_Widget_Recent_Comments` | `recent-comments` | 最近のコメント |
| `WP_Widget_RSS` | `rss` | RSS フィード |
| `WP_Widget_Tag_Cloud` | `tag_cloud` | タグクラウド |
| `WP_Widget_Custom_HTML` | `custom_html` | カスタム HTML |
| `WP_Widget_Block` | `block` | ブロックウィジェット（WordPress 5.8+） |
| `WP_Nav_Menu_Widget` | `nav_menu` | ナビゲーションメニュー |

## 7. ブロックベースウィジェット（WordPress 5.8+）

WordPress 5.8 以降、ウィジェットエリアはブロックエディタで編集可能になりました。従来のウィジェットは `WP_Widget_Block` でラップされ、ブロックエディタ内で使用されます。

### 関連関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_use_widgets_block_editor()` | `(): bool` | ブロックベースのウィジェットエディタが有効か |
| `wp_render_widget()` | `(string $widget_id, string $sidebar_id): string` | ウィジェットの HTML を取得 |
| `wp_render_widget_control()` | `(string $id): string\|null` | ウィジェットコントロールの HTML を取得 |
| `wp_find_widgets_sidebar()` | `(string $widget_id): string\|null` | ウィジェットが属するサイドバーを検索 |

### `WP_Widget_Block` の構造

```php
class WP_Widget_Block extends WP_Widget {
    // ブロックの HTML コンテンツを content キーで保持
    // $instance = ['content' => '<!-- wp:paragraph -->...<!-- /wp:paragraph -->']
}
```

## 8. REST API（WordPress 5.8+）

ブロックベースウィジェットに対応する REST API エンドポイント:

| エンドポイント | メソッド | 説明 |
|---|---|---|
| `/wp/v2/widgets` | GET | ウィジェット一覧の取得 |
| `/wp/v2/widgets` | POST | ウィジェットの作成 |
| `/wp/v2/widgets/<id>` | GET | 単一ウィジェットの取得 |
| `/wp/v2/widgets/<id>` | PUT/PATCH | ウィジェットの更新 |
| `/wp/v2/widgets/<id>` | DELETE | ウィジェットの削除 |
| `/wp/v2/sidebars` | GET | サイドバー一覧の取得 |
| `/wp/v2/sidebars/<id>` | GET | 単一サイドバーの取得 |
| `/wp/v2/sidebars/<id>` | PUT/PATCH | サイドバーの更新 |
| `/wp/v2/widget-types` | GET | ウィジェットタイプ一覧の取得 |
| `/wp/v2/widget-types/<id>` | GET | 単一ウィジェットタイプの取得 |

## 9. フック一覧

### Action フック

| フック名 | パラメータ | 説明 |
|---|---|---|
| `widgets_init` | なし | ウィジェット登録のタイミング |
| `dynamic_sidebar_before` | `string $index, bool $has_widgets` | サイドバー表示前 |
| `dynamic_sidebar_after` | `string $index, bool $has_widgets` | サイドバー表示後 |
| `dynamic_sidebar` | `array $widget` | 各ウィジェット表示時 |
| `in_widget_form` | `WP_Widget $widget, null $return, array $instance` | ウィジェットフォーム末尾 |
| `sidebar_admin_setup` | なし | ウィジェット管理画面セットアップ |
| `sidebar_admin_page` | なし | ウィジェット管理画面表示 |
| `delete_widget` | `string $widget_id, string $sidebar_id, string $id_base` | ウィジェット削除時 |

### Filter フック

| フック名 | パラメータ | 戻り値 | 説明 |
|---|---|---|---|
| `widget_display_callback` | `array $instance, WP_Widget $widget, array $args` | `array\|false` | ウィジェット表示前。`false` で表示を抑制 |
| `widget_update_callback` | `array $instance, array $new_instance, array $old_instance, WP_Widget $widget` | `array\|false` | ウィジェット更新前。`false` で更新を抑制 |
| `widget_form_callback` | `array $instance, WP_Widget $widget` | `array\|false` | ウィジェットフォーム表示前 |
| `dynamic_sidebar_params` | `array $params` | `array` | サイドバーパラメータの変更 |
| `sidebars_widgets` | `array $sidebars_widgets` | `array` | サイドバーのウィジェット割り当てをフィルタ |
| `widget_title` | `string $title, array $instance, string $id_base` | `string` | ウィジェットタイトル |
| `widget_text` | `string $text, array $instance, WP_Widget_Text $widget` | `string` | テキストウィジェットの内容 |
| `widget_text_content` | `string $text, array $instance, WP_Widget_Text $widget` | `string` | テキストウィジェットの内容（wpautop 適用後） |
| `widget_custom_html_content` | `string $content, array $instance, WP_Widget_Custom_HTML $widget` | `string` | カスタム HTML ウィジェットの内容 |
| `widget_block_content` | `string $content, array $instance, WP_Widget_Block $widget` | `string` | ブロックウィジェットの内容 |
| `is_active_sidebar` | `bool $is_active_sidebar, string $index` | `bool` | サイドバーのアクティブ状態 |
| `register_sidebar_defaults` | `array $defaults` | `array` | サイドバー登録のデフォルト値 |
| `wp_use_widgets_block_editor` | `bool $use_widgets_block_editor` | `bool` | ブロックウィジェットエディタの有効/無効 |
