# WordPress タクソノミー API 仕様

## 1. 概要

WordPress のタクソノミー（分類法）システムは、投稿やその他のオブジェクトを分類・整理するための仕組みです。ビルトインタクソノミーとして `category`（カテゴリ）、`post_tag`（タグ）、`link_category`（リンクカテゴリ）、`nav_menu`（ナビゲーションメニュー）、`post_format`（投稿フォーマット）、`wp_theme`、`wp_template_part_area`、`wp_pattern_category` が登録されており、`register_taxonomy()` でカスタムタクソノミーを追加できます。

タクソノミーシステムは 3 つのテーブル（`wp_terms`、`wp_term_taxonomy`、`wp_term_relationships`）で構成され、メタデータ用に `wp_termmeta` テーブルが追加されています。ターム（用語）の取得には `WP_Term_Query` クラスが使用されます。

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_taxonomies` | `WP_Taxonomy[]` | タクソノミー名をキーとした `WP_Taxonomy` インスタンスの配列 |

### 主要ファイル

| ファイル | 説明 |
|---|---|
| `wp-includes/class-wp-taxonomy.php` | `WP_Taxonomy` クラス |
| `wp-includes/class-wp-term.php` | `WP_Term` クラス |
| `wp-includes/class-wp-term-query.php` | `WP_Term_Query` クラス |
| `wp-includes/taxonomy.php` | タクソノミー関連関数群 |

## 2. データ構造

### WP_Term クラス

```php
class WP_Term {
    public int    $term_id;              // ターム ID
    public string $name;                 // ターム名（表示用）
    public string $slug;                 // スラッグ（URL 用）
    public int    $term_group;           // タームグループ（将来使用予定）
    public int    $term_taxonomy_id;     // wp_term_taxonomy の ID
    public string $taxonomy;             // タクソノミー名（'category', 'post_tag', 等）
    public string $description;          // 説明文
    public int    $parent;               // 親ターム ID（0 = ルート）
    public int    $count;                // このタームに属するオブジェクト数
    public string $filter;               // サニタイズコンテキスト: 'raw', 'edit', 'db', 'display'
}
```

`WP_Term::get_instance($term_id, $taxonomy)` ファクトリメソッドでオブジェクトキャッシュから取得します。

### WP_Taxonomy クラス

```php
final class WP_Taxonomy {
    // 基本設定
    public string $name;                    // タクソノミー識別子（max 32 文字）
    public string $label;                   // 表示名
    public object $labels;                  // ラベルオブジェクト
    public string $description = '';        // 説明

    // 可視性
    public bool        $public              = true;
    public bool        $publicly_queryable  = true;
    public bool        $hierarchical        = false;   // 階層型（カテゴリ）か非階層型（タグ）か
    public bool        $show_ui             = true;
    public bool        $show_in_menu        = true;
    public bool        $show_in_nav_menus   = true;
    public bool        $show_tagcloud       = true;
    public bool        $show_in_quick_edit  = true;
    public bool        $show_admin_column   = false;

    // REST API
    public bool   $show_in_rest          = false;
    public string $rest_base             = '';
    public string $rest_namespace        = 'wp/v2';
    public string $rest_controller_class = 'WP_REST_Terms_Controller';

    // 権限
    public object $cap;                    // 権限オブジェクト（manage_terms, edit_terms, delete_terms, assign_terms）

    // リライト
    public array|bool $rewrite = true;

    // クエリ
    public string|bool $query_var;         // クエリ変数名

    // コールバック
    public ?callable $update_count_callback = null;  // カウント更新コールバック
    public ?callable $meta_box_cb          = null;   // メタボックスコールバック
    public ?callable $meta_box_sanitize_cb = null;   // メタボックスサニタイズコールバック

    // 対象
    public array $object_type = [];        // 関連付けられた投稿タイプ

    // デフォルトターム
    public array|string $default_term = '';

    // ソート
    public ?bool $sort = null;

    // 内部
    public bool $_builtin = false;
}
```

### テーブル構造

タクソノミーシステムは 4 つのテーブルで構成されます:

#### wp_terms

ターム名とスラッグを格納する基本テーブル。

| カラム | 型 | 説明 |
|---|---|---|
| `term_id` | `bigint(20) unsigned` | ターム ID（PRIMARY KEY, AUTO_INCREMENT） |
| `name` | `varchar(200)` | ターム名 |
| `slug` | `varchar(200)` | スラッグ |
| `term_group` | `bigint(10)` | タームグループ ID |

**インデックス:**

| インデックス名 | カラム |
|---|---|
| `PRIMARY` | `term_id` |
| `slug` | `slug(191)` |
| `name` | `name(191)` |

#### wp_term_taxonomy

タームとタクソノミーの関連付け。同一タームが複数のタクソノミーに属することが可能です。

| カラム | 型 | 説明 |
|---|---|---|
| `term_taxonomy_id` | `bigint(20) unsigned` | PRIMARY KEY, AUTO_INCREMENT |
| `term_id` | `bigint(20) unsigned` | `wp_terms.term_id` への参照 |
| `taxonomy` | `varchar(32)` | タクソノミー名 |
| `description` | `longtext` | 説明 |
| `parent` | `bigint(20) unsigned` | 親ターム ID（階層タクソノミー用。0 = ルート） |
| `count` | `bigint(20)` | このタームに属するオブジェクト数 |

**インデックス:**

| インデックス名 | カラム |
|---|---|
| `PRIMARY` | `term_taxonomy_id` |
| `term_id_taxonomy` | `term_id, taxonomy`（UNIQUE） |
| `taxonomy` | `taxonomy` |

#### wp_term_relationships

オブジェクト（投稿等）とタームの多対多リレーションを管理するジャンクションテーブル。

| カラム | 型 | 説明 |
|---|---|---|
| `object_id` | `bigint(20) unsigned` | オブジェクト ID（通常は投稿 ID） |
| `term_taxonomy_id` | `bigint(20) unsigned` | `wp_term_taxonomy.term_taxonomy_id` への参照 |
| `term_order` | `int(11)` | 表示順（デフォルト: 0） |

**インデックス:**

| インデックス名 | カラム |
|---|---|
| `PRIMARY` | `object_id, term_taxonomy_id` |
| `term_taxonomy_id` | `term_taxonomy_id` |

#### wp_termmeta

| カラム | 型 | 説明 |
|---|---|---|
| `meta_id` | `bigint(20) unsigned` | メタ ID（PRIMARY KEY, AUTO_INCREMENT） |
| `term_id` | `bigint(20) unsigned` | ターム ID |
| `meta_key` | `varchar(255)` | メタキー |
| `meta_value` | `longtext` | メタ値 |

**インデックス:**

| インデックス名 | カラム |
|---|---|
| `PRIMARY` | `meta_id` |
| `term_id` | `term_id` |
| `meta_key` | `meta_key(191)` |

### テーブル間のリレーション

```
wp_posts
  ↓ object_id
wp_term_relationships (ジャンクション)
  ↓ term_taxonomy_id
wp_term_taxonomy
  ↓ term_id
wp_terms
  ← wp_termmeta (term_id)
```

投稿とタームの関係は `wp_term_relationships` を介した多対多リレーションです。`wp_term_taxonomy` はタームをタクソノミーに紐付け、同一のターム名（`wp_terms`）が異なるタクソノミーで使用される場合にそれぞれ独立したレコードを持ちます。

## 3. API リファレンス

### タクソノミー登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_taxonomy()` | `(string $taxonomy, array\|string $object_type, array\|string $args = []): WP_Taxonomy\|WP_Error` | タクソノミーを登録 |
| `unregister_taxonomy()` | `(string $taxonomy): true\|WP_Error` | タクソノミーの登録を解除 |
| `register_taxonomy_for_object_type()` | `(string $taxonomy, string $object_type): bool` | タクソノミーをオブジェクトタイプに関連付け |
| `unregister_taxonomy_for_object_type()` | `(string $taxonomy, string $object_type): bool` | 関連付けを解除 |
| `get_taxonomies()` | `(array $args = [], string $output = 'names', string $operator = 'and'): string[]\|WP_Taxonomy[]` | タクソノミー一覧を取得 |
| `get_taxonomy()` | `(string $taxonomy): WP_Taxonomy\|false` | タクソノミーオブジェクトを取得 |
| `taxonomy_exists()` | `(string $taxonomy): bool` | タクソノミーの存在確認 |
| `is_taxonomy_hierarchical()` | `(string $taxonomy): bool` | 階層型タクソノミーか判定 |
| `get_object_taxonomies()` | `(string\|string[]\|WP_Post $object, string $output = 'names'): string[]\|WP_Taxonomy[]` | オブジェクトタイプに関連付けられたタクソノミーを取得 |

#### `register_taxonomy()` の主要引数

| 引数 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `label` | `string` | `$taxonomy` | 表示名 |
| `labels` | `array` | 自動生成 | ラベル配列 |
| `description` | `string` | `''` | 説明 |
| `public` | `bool` | `true` | 公開するか |
| `publicly_queryable` | `bool` | `$public` | フロントエンドでクエリ可能か |
| `hierarchical` | `bool` | `false` | 階層型か（`true`: カテゴリ型、`false`: タグ型） |
| `show_ui` | `bool` | `$public` | 管理画面 UI を生成するか |
| `show_in_menu` | `bool` | `$show_ui` | メニューに表示するか |
| `show_in_nav_menus` | `bool` | `$public` | ナビゲーションメニューで利用可能か |
| `show_tagcloud` | `bool` | `$show_ui` | タグクラウドウィジェットに表示するか |
| `show_in_quick_edit` | `bool` | `$show_ui` | クイック編集に表示するか |
| `show_admin_column` | `bool` | `false` | 投稿一覧にカラムを追加するか |
| `show_in_rest` | `bool` | `false` | REST API に公開するか（ブロックエディタ対応に必須） |
| `rest_base` | `string` | `$taxonomy` | REST API ベーススラッグ |
| `rest_namespace` | `string` | `'wp/v2'` | REST API 名前空間 |
| `rest_controller_class` | `string` | `'WP_REST_Terms_Controller'` | REST コントローラークラス |
| `capabilities` | `array` | — | 権限マッピング |
| `rewrite` | `array\|bool` | `true` | リライトルール設定 |
| `query_var` | `string\|bool` | `$taxonomy` | クエリ変数名 |
| `update_count_callback` | `callable` | — | カウント更新コールバック |
| `default_term` | `string\|array` | `''` | デフォルトターム（`name`, `slug`, `description` を指定可能） |
| `sort` | `bool` | `null` | `term_order` によるソートを有効化 |
| `meta_box_cb` | `callable\|false` | — | メタボックス表示コールバック（`false` で非表示） |

#### `rewrite` 配列の詳細

| キー | 型 | デフォルト | 説明 |
|---|---|---|---|
| `slug` | `string` | `$taxonomy` | パーマリンクスラッグ |
| `with_front` | `bool` | `true` | パーマリンク構造のフロント部分を含むか |
| `hierarchical` | `bool` | `false` | 階層的な URL を許可するか（`/parent/child/`） |
| `ep_mask` | `int` | `EP_NONE` | エンドポイントマスク |

### ターム CRUD API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_insert_term()` | `(string $term, string $taxonomy, array\|string $args = []): array\|WP_Error` | タームを作成。成功時は `['term_id' => int, 'term_taxonomy_id' => int]` |
| `wp_update_term()` | `(int $term_id, string $taxonomy, array\|string $args = []): array\|WP_Error` | タームを更新 |
| `wp_delete_term()` | `(int $term_id, string $taxonomy, array\|string $args = []): bool\|int\|WP_Error` | タームを削除 |
| `get_term()` | `(int\|WP_Term $term, string $taxonomy = '', string $output = OBJECT, string $filter = 'raw'): WP_Term\|array\|WP_Error\|null` | タームを取得 |
| `get_term_by()` | `(string $field, string\|int $value, string $taxonomy = '', string $output = OBJECT, string $filter = 'raw'): WP_Term\|array\|false` | 指定フィールドでタームを取得 |
| `get_terms()` | `(array\|string $args = []): WP_Term[]\|int\|WP_Error` | ターム一覧を取得（`WP_Term_Query` ラッパー） |
| `term_exists()` | `(int\|string $term, string $taxonomy = '', int $parent_term = null): null\|int\|array` | タームの存在確認 |

#### `wp_insert_term()` / `wp_update_term()` の引数

| 引数 | 型 | 説明 |
|---|---|---|
| `alias_of` | `string` | エイリアス元のタームスラッグ |
| `description` | `string` | 説明 |
| `parent` | `int` | 親ターム ID（階層タクソノミーのみ） |
| `slug` | `string` | スラッグ |

#### `get_term_by()` の `$field` パラメータ

| 値 | 説明 |
|---|---|
| `'id'` / `'term_id'` | ターム ID |
| `'slug'` | スラッグ |
| `'name'` | ターム名 |
| `'term_taxonomy_id'` | タームタクソノミー ID |

### オブジェクト-ターム関連付け API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_set_object_terms()` | `(int $object_id, string\|int\|array $terms, string $taxonomy, bool $append = false): array\|WP_Error` | オブジェクトにタームを設定（`$append=false` で置換） |
| `wp_add_object_terms()` | `(int $object_id, string\|int\|array $terms, string $taxonomy): array\|WP_Error` | オブジェクトにタームを追加 |
| `wp_remove_object_terms()` | `(int $object_id, string\|int\|array $terms, string $taxonomy): bool\|WP_Error` | オブジェクトからタームを削除 |
| `wp_get_object_terms()` | `(int\|int[] $object_ids, string\|string[] $taxonomies, array\|string $args = []): WP_Term[]\|int[]\|string[]\|WP_Error` | オブジェクトのタームを取得 |
| `wp_get_post_terms()` | `(int $post_id = 0, string\|string[] $taxonomy = 'post_tag', array $args = []): WP_Term[]\|WP_Error` | 投稿のタームを取得 |
| `wp_set_post_terms()` | `(int $post_id = 0, string\|array $terms = '', string $taxonomy = 'post_tag', bool $append = false): array\|false\|WP_Error` | 投稿にタームを設定 |
| `wp_set_post_categories()` | `(int $post_id = 0, array\|int $post_categories = [], bool $append = false): array\|false\|WP_Error` | 投稿のカテゴリを設定 |
| `wp_set_post_tags()` | `(int $post_id = 0, string\|array $tags = '', bool $append = false): array\|false\|WP_Error` | 投稿のタグを設定 |
| `is_object_in_term()` | `(int $object_id, string $taxonomy, int\|int[]\|string\|string[] $terms = null): bool\|WP_Error` | オブジェクトが指定タームに属するか |
| `has_term()` | `(string\|int\|array $term = '', string $taxonomy = '', int\|WP_Post $post = null): bool` | 投稿が指定タームを持つか |

### ターム メタ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_term_meta()` | `(int $term_id, string $key = '', bool $single = false): mixed` | タームメタデータを取得 |
| `add_term_meta()` | `(int $term_id, string $meta_key, mixed $meta_value, bool $unique = false): int\|false\|WP_Error` | タームメタデータを追加 |
| `update_term_meta()` | `(int $term_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int\|bool\|WP_Error` | タームメタデータを更新 |
| `delete_term_meta()` | `(int $term_id, string $meta_key, mixed $meta_value = ''): bool` | タームメタデータを削除 |

### カテゴリ便利関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_categories()` | `(array\|string $args = ''): WP_Term[]\|int\|WP_Error` | カテゴリ一覧を取得 |
| `get_category()` | `(int\|object $category, string $output = OBJECT, string $filter = 'raw'): object\|null` | カテゴリを取得 |
| `get_category_by_slug()` | `(string $slug): WP_Term\|false` | スラッグからカテゴリを取得 |
| `get_cat_name()` | `(int $cat_id): string` | カテゴリ名を取得 |
| `get_cat_ID()` | `(string $cat_name = ''): int` | カテゴリ名から ID を取得 |
| `get_the_category()` | `(int $post_id = 0): WP_Term[]` | 投稿のカテゴリを取得 |
| `in_category()` | `(int\|string\|int[]\|string[] $category, int\|WP_Post $post = null): bool` | 投稿が指定カテゴリに属するか |
| `cat_is_ancestor_of()` | `(int\|WP_Term $cat1, int\|WP_Term $cat2): bool` | カテゴリが祖先か判定 |
| `get_category_parents()` | `(int $category_id, bool $link = false, string $separator = '/', bool $nicename = false, array $deprecated = []): string\|WP_Error` | カテゴリの親パスを取得 |

### タグ便利関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_tags()` | `(array\|string $args = ''): WP_Term[]\|int\|WP_Error` | タグ一覧を取得 |
| `get_tag()` | `(int\|WP_Term\|object $tag, string $output = OBJECT, string $filter = 'raw'): WP_Term\|array\|WP_Error\|null` | タグを取得 |
| `get_the_tags()` | `(int $post_id = 0): WP_Term[]\|false` | 投稿のタグを取得 |
| `has_tag()` | `(string\|int\|array $tag = '', int\|WP_Post $post = null): bool` | 投稿が指定タグを持つか |

### WP_Term_Query

`WP_Term_Query` はタームを検索するためのクラスで、`get_terms()` の内部実装として使用されます。

#### 主要パラメータ

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `taxonomy` | `string\|string[]` | — | タクソノミー名 |
| `object_ids` | `int\|int[]` | — | 対象オブジェクト ID |
| `orderby` | `string` | `'name'` | ソートフィールド |
| `order` | `string` | `'ASC'` | ソート方向 |
| `hide_empty` | `bool` | `true` | 投稿が紐付いていないタームを除外 |
| `include` | `int[]\|string` | `[]` | 含めるターム ID |
| `exclude` | `int[]\|string` | `[]` | 除外するターム ID |
| `exclude_tree` | `int[]\|string` | `[]` | 除外するタームとその子孫 |
| `number` | `int\|string` | `''` | 取得件数 |
| `offset` | `int` | `''` | スキップ件数 |
| `fields` | `string` | `'all'` | 取得フィールド |
| `count` | `bool` | `false` | ターム数のみ返す |
| `name` | `string\|string[]` | `''` | ターム名で検索 |
| `slug` | `string\|string[]` | `''` | スラッグで検索 |
| `term_taxonomy_id` | `int\|int[]` | `''` | タームタクソノミー ID で検索 |
| `hierarchical` | `bool` | `true` | 階層を考慮するか |
| `search` | `string` | `''` | 名前・スラッグの部分一致検索 |
| `name__like` | `string` | `''` | ターム名の部分一致 |
| `description__like` | `string` | `''` | 説明の部分一致 |
| `pad_counts` | `bool` | `false` | 子タームの数を親に加算 |
| `get` | `string` | `''` | `'all'` で `hide_empty`, `child_of` を無視 |
| `child_of` | `int` | `0` | 指定タームの子孫のみ取得 |
| `parent` | `int\|string` | `''` | 直接の親ターム ID |
| `childless` | `bool` | `false` | 子タームを持たないタームのみ |
| `cache_domain` | `string` | `'core'` | キャッシュキーのプレフィックス |
| `cache_results` | `bool` | `true` | 結果をキャッシュするか |
| `update_term_meta_cache` | `bool` | `true` | タームメタキャッシュを更新するか |
| `meta_query` | `array` | `[]` | メタクエリ配列（`WP_Meta_Query` と同じ構文） |
| `meta_key` | `string` | `''` | メタキー |
| `meta_value` | `string` | `''` | メタ値 |
| `meta_type` | `string` | `''` | メタ型 |
| `meta_compare` | `string` | `''` | 比較演算子 |

#### `orderby` で指定できる値

| 値 | 説明 |
|---|---|
| `'name'` | ターム名（デフォルト） |
| `'slug'` | スラッグ |
| `'term_group'` | タームグループ |
| `'term_id'` / `'id'` | ターム ID |
| `'description'` | 説明 |
| `'parent'` | 親ターム ID |
| `'count'` | オブジェクト数 |
| `'include'` | `include` パラメータの順序 |
| `'slug__in'` | `slug` パラメータの順序 |
| `'meta_value'` | メタ値（`meta_key` 必須） |
| `'meta_value_num'` | メタ値（数値） |
| `'none'` | ソートなし |
| 名前付き `meta_query` 句 | メタクエリ句名 |

#### `fields` で指定できる値

| 値 | 返り値の型 |
|---|---|
| `'all'` | `WP_Term[]`（デフォルト） |
| `'all_with_object_id'` | `WP_Term[]`（`object_id` プロパティ付き） |
| `'ids'` | `int[]`（ターム ID） |
| `'tt_ids'` | `int[]`（タームタクソノミー ID） |
| `'names'` | `string[]`（ターム名） |
| `'slugs'` | `string[]`（スラッグ） |
| `'count'` | `string`（件数） |
| `'id=>name'` | `string[]`（ID をキーとした名前の連想配列） |
| `'id=>slug'` | `string[]`（ID をキーとしたスラッグの連想配列） |
| `'id=>parent'` | `int[]`（ID をキーとした親 ID の連想配列） |

### ターム数カウント API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_update_term_count()` | `(int\|array $terms, string $taxonomy, bool $do_deferred = false): bool` | タームのカウントを更新 |
| `wp_update_term_count_now()` | `(array $terms, string $taxonomy): true` | 即座にカウントを更新 |
| `wp_defer_term_counting()` | `(bool $defer = null): bool` | カウント更新の遅延制御 |

## 4. 実行フロー

### `register_taxonomy()` の実行フロー

```
register_taxonomy('genre', 'book', $args)
│
├── バリデーション
│   ├── $taxonomy が 32 文字以内か確認
│   ├── 予約語チェック
│   └── 重複登録チェック
│
├── new WP_Taxonomy('genre', 'book', $args)
│   ├── set_props($args)
│   │   ├── デフォルト値のマージ
│   │   ├── $public から $show_ui, $show_in_nav_menus 等を推論
│   │   └── ケイパビリティの生成
│   │       ├── manage_terms → manage_categories
│   │       ├── edit_terms → manage_categories
│   │       ├── delete_terms → manage_categories
│   │       └── assign_terms → edit_posts
│   │
│   └── add_rewrite_rules()
│       ├── add_rewrite_tag('%genre%', '([^/]+)')
│       └── add_permastruct('genre', 'genre/%genre%', ...)
│
├── $wp_taxonomies['genre'] = $taxonomy_object
│
├── オブジェクトタイプとの関連付け
│   └── $wp_taxonomies['genre']->object_type[] = 'book'
│
├── デフォルトタームの登録（指定時）
│   └── wp_insert_term($default_term, 'genre')
│
├── do_action('registered_taxonomy', 'genre', 'book', $args)
│
└── return $taxonomy_object
```

### `wp_insert_term()` の実行フロー

```
wp_insert_term('Science Fiction', 'genre', ['slug' => 'sci-fi', 'parent' => 5])
│
├── 引数のサニタイズ
│   ├── $term = sanitize_term($term, $taxonomy, 'db')
│   ├── $slug = wp_unique_term_slug($slug, $term_object)
│   └── $parent のバリデーション（階層タクソノミーのみ）
│
├── apply_filters('pre_insert_term', $term, $taxonomy)
│
├── 重複チェック
│   └── term_exists($term, $taxonomy, $parent)
│       └── 既存なら WP_Error を返す
│
├── wp_terms テーブルへの INSERT
│   └── $wpdb->insert($wpdb->terms, ['name' => ..., 'slug' => ..., 'term_group' => ...])
│   └── $term_id = $wpdb->insert_id
│
├── wp_term_taxonomy テーブルへの INSERT
│   └── $wpdb->insert($wpdb->term_taxonomy, [
│       'term_id' => $term_id,
│       'taxonomy' => 'genre',
│       'description' => ...,
│       'parent' => 5,
│       'count' => 0,
│   ])
│   └── $tt_id = $wpdb->insert_id
│
├── キャッシュクリア
│   └── clean_term_cache($term_id, $taxonomy)
│
├── do_action('created_term', $term_id, $tt_id, $taxonomy, $args)
├── do_action("create_{$taxonomy}", $term_id, $tt_id, $args)
│
└── return ['term_id' => $term_id, 'term_taxonomy_id' => $tt_id]
```

### `wp_set_object_terms()` の実行フロー

```
wp_set_object_terms($post_id, ['sci-fi', 'action'], 'genre', $append = false)
│
├── タクソノミーの検証
│   └── taxonomy_exists('genre') チェック
│
├── 各タームの解決
│   ├── 'sci-fi' → get_term_by('slug', 'sci-fi', 'genre')
│   │   └── 存在しない場合: wp_insert_term('sci-fi', 'genre')
│   └── 'action' → get_term_by('slug', 'action', 'genre')
│       └── 存在しない場合: wp_insert_term('action', 'genre')
│
├── $append == false の場合
│   ├── 既存のターム関連を取得
│   └── 不要な関連を wp_term_relationships から DELETE
│       └── 各削除ターム: wp_update_term_count()
│
├── 新規関連の INSERT
│   └── $wpdb->insert($wpdb->term_relationships, [
│       'object_id' => $post_id,
│       'term_taxonomy_id' => $tt_id,
│   ])
│
├── wp_update_term_count($tt_ids, 'genre')
│
├── clean_object_term_cache($post_id, 'genre')
│
├── do_action('set_object_terms', $post_id, $terms, $tt_ids, 'genre', $append, $old_tt_ids)
│
└── return $tt_ids
```

### WP_Term_Query の実行フロー

```
new WP_Term_Query(['taxonomy' => 'genre', 'hide_empty' => false])
│
├── $this->query($args)
│   │
│   ├── $this->parse_query($args)
│   │   └── デフォルト値のマージ
│   │
│   └── $this->get_terms()
│       │
│       ├── apply_filters('pre_get_terms', $this)
│       │
│       ├── キャッシュチェック
│       │   └── wp_cache_get($cache_key, 'term-queries')
│       │       └── ヒット: キャッシュから返す
│       │
│       ├── SQL 構築
│       │   ├── SELECT: $fields パラメータに基づく
│       │   │
│       │   ├── FROM: $wpdb->terms AS t
│       │   │   INNER JOIN $wpdb->term_taxonomy AS tt ON t.term_id = tt.term_id
│       │   │
│       │   ├── WHERE:
│       │   │   ├── tt.taxonomy IN ('genre')
│       │   │   ├── hide_empty → tt.count > 0
│       │   │   ├── include/exclude → t.term_id IN/NOT IN (...)
│       │   │   ├── name/slug → t.name = ... / t.slug = ...
│       │   │   ├── search → t.name LIKE '%...%' OR t.slug LIKE '%...%'
│       │   │   ├── parent → tt.parent = ...
│       │   │   ├── child_of → 再帰的に子孫 ID を収集
│       │   │   └── meta_query → WP_Meta_Query::get_sql()
│       │   │
│       │   ├── object_ids 指定時
│       │   │   └── JOIN $wpdb->term_relationships AS tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
│       │   │       WHERE tr.object_id IN (...)
│       │   │
│       │   ├── ORDER BY: $orderby パラメータに基づく
│       │   └── LIMIT: $number / $offset
│       │
│       ├── apply_filters('terms_clauses', $clauses, $taxonomies, $args)
│       │
│       ├── $wpdb->get_results($sql)
│       │
│       ├── WP_Term オブジェクトへの変換
│       │   └── get_term($row)
│       │
│       ├── pad_counts 処理（階層タクソノミー）
│       │   └── _pad_term_counts($terms, $taxonomy)
│       │       └── 子タームの count を再帰的に親に加算
│       │
│       ├── キャッシュに格納
│       │   └── wp_cache_set($cache_key, $term_ids, 'term-queries')
│       │
│       └── return $this->terms
```

## 5. フック一覧

### タクソノミー登録

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `registered_taxonomy` | Action | `(string $taxonomy, string\|array $object_type, array $args)` | タクソノミー登録後 |
| `unregistered_taxonomy` | Action | `(string $taxonomy)` | タクソノミー登録解除後 |
| `register_taxonomy_args` | Filter | `(array $args, string $taxonomy, string[] $object_type): array` | 登録引数をフィルタ |

### ターム CRUD

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `pre_insert_term` | Filter | `(string $term, string $taxonomy): string\|WP_Error` | ターム挿入前。WP_Error を返すと挿入中止 |
| `created_term` | Action | `(int $term_id, int $tt_id, string $taxonomy, array $args)` | ターム作成後（全タクソノミー） |
| `create_{$taxonomy}` | Action | `(int $term_id, int $tt_id, array $args)` | 特定タクソノミーのターム作成後 |
| `edited_term` | Action | `(int $term_id, int $tt_id, string $taxonomy, array $args)` | ターム更新後（全タクソノミー） |
| `edit_{$taxonomy}` | Action | `(int $term_id, int $tt_id, array $args)` | 特定タクソノミーのターム更新後 |
| `saved_term` | Action | `(int $term_id, int $tt_id, string $taxonomy, bool $update, array $args)` | ターム作成・更新後 |
| `pre_delete_term` | Action | `(int $term_id, string $taxonomy)` | ターム削除前 |
| `delete_term` | Action | `(int $term_id, int $tt_id, string $taxonomy, WP_Term $deleted_term, int[] $object_ids)` | ターム削除後 |
| `delete_{$taxonomy}` | Action | `(int $term_id, int $tt_id, WP_Term $deleted_term, int[] $object_ids)` | 特定タクソノミーのターム削除後 |
| `clean_term_cache` | Action | `(array $ids, string $taxonomy)` | タームキャッシュクリア後 |

### ターム取得

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `get_term` | Filter | `(WP_Term $term, string $taxonomy): WP_Term` | ターム取得時 |
| `get_{$taxonomy}` | Filter | `(WP_Term $term, string $taxonomy): WP_Term` | 特定タクソノミーのターム取得時 |
| `get_terms` | Filter | `(WP_Term[] $terms, string[] $taxonomies, array $args, WP_Term_Query $query): WP_Term[]` | ターム一覧取得時 |
| `pre_get_terms` | Action | `(WP_Term_Query &$query)` | タームクエリ実行前 |
| `terms_clauses` | Filter | `(array $clauses, string[] $taxonomies, array $args): array` | ターム SQL 句をフィルタ |
| `get_terms_defaults` | Filter | `(array $defaults, string[] $taxonomies): array` | デフォルトクエリ引数をフィルタ |
| `terms_pre_query` | Filter | `(mixed $terms, WP_Term_Query $query): mixed` | クエリのショートサーキット |

### オブジェクト-ターム関連

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `set_object_terms` | Action | `(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids)` | オブジェクトのターム設定後 |
| `added_term_relationship` | Action | `(int $object_id, int $tt_id, string $taxonomy)` | ターム関連追加後 |
| `deleted_term_relationships` | Action | `(int $object_id, array $tt_ids, string $taxonomy)` | ターム関連削除後 |
| `wp_get_object_terms` | Filter | `(WP_Term[] $terms, int[] $object_ids, string[] $taxonomies, array $args): WP_Term[]` | オブジェクトのターム取得時 |

### ターム表示

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `term_link` | Filter | `(string $termlink, WP_Term $term, string $taxonomy): string` | タームアーカイブ URL |
| `the_category` | Filter | `(string $thelist, string $separator, string $parents): string` | カテゴリ一覧の HTML |
| `the_tags` | Filter | `(string $tag_list, string $before, string $sep, string $after, int $post_id): string` | タグ一覧の HTML |
| `term_name` | Filter | `(string $name, WP_Term $term): string` | ターム名表示時 |
| `term_description` | Filter | `(string $description, int $term_id, string $taxonomy, WP_Term $term): string` | ターム説明表示時 |
