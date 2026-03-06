# WordPress ナビゲーションメニュー API 仕様

## 1. 概要

WordPress のナビゲーションメニュー API は、サイトのメニューを作成・管理・表示するための仕組みです。メニューはカスタムタクソノミー `nav_menu` として保存され、メニュー項目はカスタム投稿タイプ `nav_menu_item` として管理されます。テーマは `register_nav_menus()` でメニュー位置（ロケーション）を定義し、ユーザーは管理画面でメニューをロケーションに割り当てます。

メニューシステムの主要コンポーネント:

| コンポーネント | 型 / テーブル | 説明 |
|---|---|---|
| メニュー | `WP_Term`（`nav_menu` タクソノミー） | メニュー自体。名前とスラッグを持つ |
| メニュー項目 | `WP_Post`（`nav_menu_item` 投稿タイプ） | メニューの各項目。投稿・ページ・カテゴリ・カスタムURL等 |
| メニューロケーション | `theme_mods` オプション | テーマが定義するメニュー配置位置 |
| Walker | `Walker_Nav_Menu` | メニュー項目をHTMLに変換する Walker クラス |

## 2. データ構造

### メニュー（nav_menu タクソノミー）

メニューは `wp_terms` テーブルに保存されます:

| カラム | 説明 |
|---|---|
| `term_id` | メニューID |
| `name` | メニュー名（例: "Main Menu"） |
| `slug` | メニュースラッグ |

### メニュー項目（nav_menu_item 投稿タイプ）

メニュー項目は `wp_posts` テーブルに保存され、メタデータで詳細情報を管理します:

| カラム / メタキー | 説明 |
|---|---|
| `ID` | メニュー項目ID |
| `post_title` | ナビゲーションラベル |
| `post_status` | `publish` または `draft` |
| `post_parent` | 常に 0（階層は postmeta で管理） |
| `menu_order` | 表示順序 |
| `post_type` | `nav_menu_item` |
| `_menu_item_type` | 項目タイプ: `post_type`, `taxonomy`, `custom`, `post_type_archive` |
| `_menu_item_menu_item_parent` | 親メニュー項目のID（0 = トップレベル） |
| `_menu_item_object_id` | リンク先オブジェクトのID |
| `_menu_item_object` | オブジェクトタイプ（`page`, `post`, `category` 等） |
| `_menu_item_target` | リンクターゲット（`_blank` 等） |
| `_menu_item_classes` | CSS クラスの配列（シリアライズ） |
| `_menu_item_xfn` | XFN リレーション |
| `_menu_item_url` | カスタム URL（`custom` タイプの場合） |
| `_menu_item_invalid` | 無効なメニュー項目フラグ |

### メニュー項目のオブジェクト表現

`wp_setup_nav_menu_item()` がメニュー項目の `WP_Post` オブジェクトにメタ情報をプロパティとして追加します:

```php
// wp_setup_nav_menu_item() 適用後の WP_Post オブジェクト
$menu_item->menu_item_parent  // 親メニュー項目ID
$menu_item->object_id         // リンク先オブジェクトID
$menu_item->object            // オブジェクトタイプ（page, category 等）
$menu_item->type              // 項目タイプ（post_type, taxonomy, custom）
$menu_item->type_label        // タイプの表示名（"ページ", "カテゴリー"等）
$menu_item->title             // ナビゲーションラベル
$menu_item->url               // リンクURL
$menu_item->target            // リンクターゲット
$menu_item->attr_title        // title属性
$menu_item->description       // 説明
$menu_item->classes           // CSSクラスの配列
$menu_item->xfn               // XFNリレーション
$menu_item->current           // 現在のページか（bool）
$menu_item->current_item_ancestor  // 現在ページの祖先か
$menu_item->current_item_parent    // 現在ページの親か
```

### メニューロケーション

テーマのメニューロケーション割り当ては `theme_mods_{theme_slug}` オプションの `nav_menu_locations` に保存されます:

```php
// get_theme_mod('nav_menu_locations') の値
[
    'primary'   => 5,   // term_id
    'secondary' => 12,
    'footer'    => 8,
]
```

### Walker_Nav_Menu クラス

メニュー項目をツリー構造から HTML に変換する Walker クラスです:

```php
class Walker_Nav_Menu extends Walker {
    public $tree_type  = ['post_type', 'taxonomy', 'custom'];
    public $db_fields  = [
        'parent' => 'menu_item_parent',
        'id'     => 'db_id',
    ];
}
```

## 3. API リファレンス

### メニュー登録 API（テーマ用）

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_nav_menus()` | `(array $locations = []): void` | メニューロケーションを登録。`['location-slug' => '表示名']` |
| `unregister_nav_menu()` | `(string $location): bool` | メニューロケーションを解除 |
| `register_nav_menu()` | `(string $location, string $description): void` | 単一のメニューロケーションを登録 |

### メニュー CRUD API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_create_nav_menu()` | `(string $menu_name): int\|WP_Error` | メニューを作成。term_id を返す |
| `wp_update_nav_menu_object()` | `(int $menu_id = 0, array $menu_data = []): int\|WP_Error` | メニューを更新/作成 |
| `wp_delete_nav_menu()` | `(int\|string\|WP_Term $menu): bool\|WP_Error` | メニューと全項目を削除 |
| `wp_get_nav_menus()` | `(array $args = []): WP_Term[]` | 全メニューを取得 |
| `wp_get_nav_menu_object()` | `(int\|string\|WP_Term $menu): WP_Term\|false` | メニューオブジェクトを取得 |

### メニュー項目 CRUD API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_update_nav_menu_item()` | `(int $menu_id = 0, int $menu_item_db_id = 0, array $menu_item_data = []): int\|WP_Error` | メニュー項目を作成/更新 |
| `wp_get_nav_menu_items()` | `(int\|string\|WP_Term $menu, array $args = []): array\|false` | メニューの全項目を取得 |
| `wp_setup_nav_menu_item()` | `(object $menu_item): object` | メニュー項目にメタ情報を追加 |

### 表示 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_nav_menu()` | `(array $args = []): void\|string\|false` | ナビゲーションメニューを表示 |
| `wp_page_menu()` | `(array\|string $args = []): void\|string` | ページベースのフォールバックメニュー |
| `walk_nav_menu_tree()` | `(array $items, int $depth, stdClass $args): string` | メニューツリーを Walker で HTML に変換 |

### ロケーション API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_registered_nav_menus()` | `(): array` | 登録済みメニューロケーション一覧を取得 |
| `get_nav_menu_locations()` | `(): array` | ロケーションとメニューの対応を取得 |
| `set_theme_mod()` | `('nav_menu_locations', array $locations): void` | ロケーション割り当てを保存 |
| `has_nav_menu()` | `(string $location): bool` | ロケーションにメニューが割り当てられているか |

### クエリ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `is_nav_menu()` | `(int\|string\|WP_Term $menu): bool` | ナビゲーションメニューか判定 |
| `is_nav_menu_item()` | `(int\|WP_Post $post = 0): bool` | メニュー項目か判定 |
| `wp_get_associated_nav_menu_items()` | `(int $object_id = 0, string $object_type = 'post_type', string $taxonomy = ''): int[]` | オブジェクトに関連するメニュー項目IDを取得 |

## 4. 実行フロー

### wp_nav_menu() の表示フロー

```
wp_nav_menu(['theme_location' => 'primary', 'depth' => 2])
│
├── $args をデフォルト値とマージ
│   ├── theme_location, menu, container, container_class,
│   │   container_id, menu_class, menu_id, echo, fallback_cb,
│   │   before, after, link_before, link_after, items_wrap, depth,
│   │   walker, item_spacing
│   │
│   └── apply_filters('wp_nav_menu_args', $args)
│
├── メニューの解決
│   ├── theme_location が指定されている場合
│   │   └── get_nav_menu_locations() からメニューID取得
│   ├── $args['menu'] が指定されている場合
│   │   └── wp_get_nav_menu_object() で取得
│   └── フォールバック: 最初のメニューを使用
│
├── メニューが見つからない場合
│   └── $args['fallback_cb'] を呼び出し（デフォルト: wp_page_menu）
│
├── $menu_items = wp_get_nav_menu_items($menu->term_id)
│   └── 各項目に wp_setup_nav_menu_item() を適用
│
├── 現在のページ情報でクラスを設定
│   ├── current-menu-item
│   ├── current-menu-parent
│   ├── current-menu-ancestor
│   └── menu-item-has-children
│
├── apply_filters('wp_nav_menu_objects', $sorted_menu_items, $args)
│
├── $items = walk_nav_menu_tree($sorted_menu_items, $depth, $args)
│   └── Walker_Nav_Menu::walk()
│       ├── start_lvl(): <ul class="sub-menu"> を開く
│       ├── start_el(): <li> と <a> を出力
│       │   └── apply_filters('nav_menu_link_attributes', $atts, $menu_item, $args, $depth)
│       │   └── apply_filters('nav_menu_item_title', $title, $menu_item, $args, $depth)
│       ├── end_el(): </li> を閉じる
│       └── end_lvl(): </ul> を閉じる
│
├── $items = apply_filters('wp_nav_menu_items', $items, $args)
│
├── $nav_menu = sprintf($args['items_wrap'], $menu_id, $menu_class, $items)
│   └── container でラップ（div/nav）
│
├── $nav_menu = apply_filters('wp_nav_menu', $nav_menu, $args)
│
└── echo または return
```

### wp_nav_menu() の主要引数

| 引数 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `theme_location` | `string` | `''` | テーマのメニューロケーション |
| `menu` | `int\|string\|WP_Term` | `''` | メニューID、スラッグ、名前、またはオブジェクト |
| `container` | `string\|false` | `'div'` | ラッパー要素。`false` で無効化 |
| `container_class` | `string` | `'menu-{slug}-container'` | コンテナの CSS クラス |
| `container_id` | `string` | `''` | コンテナの ID |
| `menu_class` | `string` | `'menu'` | `<ul>` の CSS クラス |
| `menu_id` | `string` | `'{slug}'` | `<ul>` の ID |
| `fallback_cb` | `callable\|false` | `'wp_page_menu'` | メニュー未設定時のフォールバック |
| `before` | `string` | `''` | `<a>` タグの前に挿入する HTML |
| `after` | `string` | `''` | `<a>` タグの後に挿入する HTML |
| `link_before` | `string` | `''` | リンクテキストの前に挿入 |
| `link_after` | `string` | `''` | リンクテキストの後に挿入 |
| `depth` | `int` | `0` | メニューの深さ（0 = 無制限） |
| `walker` | `Walker\|false` | `false` | カスタム Walker クラス |
| `items_wrap` | `string` | `'<ul id="%1$s" class="%2$s">%3$s</ul>'` | メニューの HTML テンプレート |
| `item_spacing` | `string` | `'preserve'` | `'preserve'` または `'discard'` |

### メニュー項目の CSS クラス

`wp_nav_menu()` は各メニュー項目に自動的に CSS クラスを追加します:

| クラス | 条件 |
|---|---|
| `menu-item` | 全項目 |
| `menu-item-type-{type}` | 項目タイプ別（`post_type`, `taxonomy`, `custom`） |
| `menu-item-object-{object}` | オブジェクト別（`page`, `category` 等） |
| `menu-item-has-children` | 子項目がある場合 |
| `current-menu-item` | 現在表示中のページ |
| `current-menu-parent` | 現在ページの親メニュー項目 |
| `current-menu-ancestor` | 現在ページの祖先メニュー項目 |
| `current_{object}_item` | 現在の投稿タイプ/タクソノミーに対応する項目 |
| `current_{object}_parent` | 現在のオブジェクトの親 |
| `current_{object}_ancestor` | 現在のオブジェクトの祖先 |
| `menu-item-home` | フロントページ |
| `menu-item-privacy-policy` | プライバシーポリシーページ |
| `page_item_has_children` | ページがサブページを持つ場合 |

## 5. フック一覧

### Action フック

| フック名 | パラメータ | 説明 |
|---|---|---|
| `wp_create_nav_menu` | `int $term_id, array $menu_data` | メニュー作成後 |
| `wp_update_nav_menu` | `int $menu_id, array $menu_data, array $old_menu_data` | メニュー更新後 |
| `wp_delete_nav_menu` | `int $term_id` | メニュー削除後 |
| `wp_update_nav_menu_item` | `int $menu_id, int $menu_item_db_id, array $menu_item_data` | メニュー項目更新後 |
| `wp_add_nav_menu_item` | `int $menu_id, int $menu_item_db_id` | メニュー項目追加後 |
| `delete_nav_menu_item` | `int $menu_item_id` | メニュー項目削除時（`before_delete_post` を使用） |

### Filter フック

| フック名 | パラメータ | 戻り値 | 説明 |
|---|---|---|---|
| `wp_nav_menu_args` | `array $args` | `array` | `wp_nav_menu()` の引数 |
| `wp_nav_menu_objects` | `array $sorted_menu_items, stdClass $args` | `array` | メニュー項目のオブジェクト配列 |
| `wp_nav_menu_items` | `string $items, stdClass $args` | `string` | メニューの HTML アイテム |
| `wp_nav_menu_{$menu->slug}_items` | `string $items, stdClass $args` | `string` | 特定メニューの HTML アイテム |
| `wp_nav_menu` | `string $nav_menu, stdClass $args` | `string` | 最終的なメニュー HTML |
| `nav_menu_link_attributes` | `array $atts, WP_Post $menu_item, stdClass $args, int $depth` | `array` | リンクの HTML 属性 |
| `nav_menu_item_title` | `string $title, WP_Post $menu_item, stdClass $args, int $depth` | `string` | メニュー項目のタイトル |
| `nav_menu_css_class` | `string[] $classes, WP_Post $menu_item, stdClass $args, int $depth` | `string[]` | メニュー項目の CSS クラス |
| `nav_menu_item_id` | `string $menu_item_id, WP_Post $menu_item, stdClass $args, int $depth` | `string` | メニュー項目の ID 属性 |
| `nav_menu_item_args` | `stdClass $args, WP_Post $menu_item, int $depth` | `stdClass` | メニュー項目の引数 |
| `wp_setup_nav_menu_item` | `object $menu_item` | `object` | メニュー項目のセットアップ後 |
| `wp_get_nav_menu_items` | `array $items, WP_Term $menu, array $args` | `array` | メニュー項目の取得結果 |
| `wp_get_nav_menus` | `WP_Term[] $menus, array $args` | `WP_Term[]` | メニュー一覧の取得結果 |
| `has_nav_menu` | `bool $has_nav_menu, string $location` | `bool` | メニューの割り当て状態 |
| `wp_nav_menu_empty_trash_expire` | `int $expire_days` | `int` | メニュー項目のゴミ箱保持日数（デフォルト 0 = 即削除） |
| `walker_nav_menu_start_el` | `string $item_output, WP_Post $menu_item, int $depth, stdClass $args` | `string` | Walker の項目開始 HTML |
