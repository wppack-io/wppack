# WordPress 投稿タイプ・WP_Query 仕様

## 1. 概要

WordPress の投稿タイプシステムは、コンテンツの種類を定義・管理する仕組みです。デフォルトで `post`、`page`、`attachment`、`revision`、`nav_menu_item` の 5 つの組み込み投稿タイプが登録されており、`register_post_type()` で独自の投稿タイプを追加できます。

投稿タイプシステムは以下のグローバル変数で状態を管理します:

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_post_types` | `WP_Post_Type[]` | 投稿タイプ名をキーとした `WP_Post_Type` インスタンスの配列 |
| `$wp_query` | `WP_Query` | メインクエリのインスタンス |
| `$post` | `WP_Post\|null` | 現在のループで処理中の投稿オブジェクト |
| `$posts` | `WP_Post[]` | メインクエリの結果配列（テンプレート内） |

投稿データの取得は `WP_Query` クラスが担当し、SQL の生成からキャッシュまで一貫して処理します。

## 2. データ構造

### WP_Post クラス

`WP_Post` は `final` クラスで、`wp_posts` テーブルの 1 行を表します。

```php
final class WP_Post {
    public int    $ID;
    public int    $post_author;
    public string $post_date;              // 'Y-m-d H:i:s'
    public string $post_date_gmt;
    public string $post_content;
    public string $post_title;
    public string $post_excerpt;
    public string $post_status;            // 'publish', 'draft', 'pending', 'private', 'trash', ...
    public string $comment_status;         // 'open', 'closed'
    public string $ping_status;            // 'open', 'closed'
    public string $post_password;
    public string $post_name;              // スラッグ
    public string $to_ping;
    public string $pinged;
    public string $post_modified;
    public string $post_modified_gmt;
    public string $post_content_filtered;
    public int    $post_parent;
    public string $guid;
    public int    $menu_order;
    public string $post_type;              // 'post', 'page', カスタム投稿タイプ名
    public string $post_mime_type;
    public int    $comment_count;
    public string $filter;                 // サニタイズレベル: 'raw', 'edit', 'db', 'display'
}
```

`WP_Post` はマジックメソッド `__get()` を実装しており、`$post->ancestors`、`$post->page_template`、`$post->post_category`、`$post->tags_input` への遅延アクセスを提供します。

### WP_Post_Type クラス

`WP_Post_Type` は投稿タイプの設定を保持するクラスです。`register_post_type()` で生成されます。

```php
final class WP_Post_Type {
    // 基本設定
    public string $name;
    public string $label;
    public object $labels;
    public string $description;

    // 可視性
    public bool        $public;
    public bool        $hierarchical;
    public bool        $exclude_from_search;
    public bool        $publicly_queryable;
    public bool        $show_ui;
    public bool|string $show_in_menu;
    public bool        $show_in_nav_menus;
    public bool        $show_in_admin_bar;
    public ?int        $menu_position;
    public ?string     $menu_icon;

    // 権限
    public string $capability_type;
    public bool   $map_meta_cap;
    public object $cap;

    // 機能
    public array      $taxonomies;
    public bool|string $has_archive;
    public array|bool  $supports;
    public array|bool  $rewrite;
    public string|bool $query_var;
    public bool        $can_export;
    public ?bool       $delete_with_user;

    // REST API
    public bool        $show_in_rest;
    public string|bool $rest_base;
    public string|bool $rest_namespace;
    public string|bool $rest_controller_class;

    // テンプレート
    public array       $template;
    public string|bool $template_lock;

    // 内部
    public bool   $_builtin;
    public string $_edit_link;
}
```

### WP_Query クラス

`WP_Query` は投稿取得の中心クラスで、クエリパラメータの解析、SQL 生成、結果のループ処理を担います。

```php
class WP_Query {
    // クエリ入力
    public string $query_vars_hash;
    public array  $query_vars = [];
    public string $request;                // 生成された SQL

    // クエリ結果
    public WP_Post[] $posts;
    public int       $post_count;          // 現在ページの投稿数
    public int       $found_posts;         // 条件に一致する全投稿数
    public int       $max_num_pages;       // 総ページ数

    // ループ状態
    public int      $current_post = -1;
    public ?WP_Post $post;                 // 現在の投稿
    public bool     $in_the_loop = false;

    // コンディショナルタグ（is_* プロパティ群）
    public bool $is_single       = false;
    public bool $is_preview      = false;
    public bool $is_page         = false;
    public bool $is_archive      = false;
    public bool $is_date         = false;
    public bool $is_year         = false;
    public bool $is_month        = false;
    public bool $is_day          = false;
    public bool $is_time         = false;
    public bool $is_author       = false;
    public bool $is_category     = false;
    public bool $is_tag          = false;
    public bool $is_tax          = false;
    public bool $is_search       = false;
    public bool $is_feed         = false;
    public bool $is_comment_feed = false;
    public bool $is_home         = false;
    public bool $is_404          = false;
    public bool $is_paged        = false;
    public bool $is_admin        = false;
    public bool $is_attachment   = false;
    public bool $is_singular     = false;
    public bool $is_robots       = false;
    public bool $is_favicon      = false;
    public bool $is_posts_page   = false;
    public bool $is_post_type_archive = false;
    // ... 他多数
}
```

## 3. API リファレンス

### 投稿タイプ登録 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `register_post_type()` | `(string $post_type, array\|string $args = []): WP_Post_Type\|WP_Error` | カスタム投稿タイプを登録 |
| `unregister_post_type()` | `(string $post_type): true\|WP_Error` | 投稿タイプの登録解除 |
| `get_post_type_object()` | `(string $post_type): ?WP_Post_Type` | 投稿タイプオブジェクトを取得 |
| `get_post_types()` | `(array\|string $args = [], string $output = 'names', string $operator = 'and'): string[]\|WP_Post_Type[]` | 登録済み投稿タイプ一覧を取得 |
| `post_type_exists()` | `(string $post_type): bool` | 投稿タイプの存在確認 |
| `post_type_supports()` | `(string $post_type, string $feature): bool` | 投稿タイプが機能をサポートしているか確認 |
| `add_post_type_support()` | `(string $post_type, string\|array $feature, mixed ...$args): void` | 投稿タイプに機能を追加 |
| `remove_post_type_support()` | `(string $post_type, string $feature): void` | 投稿タイプから機能を削除 |

#### `register_post_type()` の `$args` パラメータ

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `label` | `string` | `$post_type` | メニューに表示される名前 |
| `labels` | `array` | 自動生成 | 各コンテキスト用のラベル配列 |
| `description` | `string` | `''` | 投稿タイプの説明 |
| `public` | `bool` | `false` | 管理画面・フロントエンドでの公開性 |
| `hierarchical` | `bool` | `false` | 階層構造の有無 |
| `exclude_from_search` | `bool` | `!$public` | 検索結果からの除外 |
| `publicly_queryable` | `bool` | `$public` | フロントエンドでのクエリ可否 |
| `show_ui` | `bool` | `$public` | 管理画面 UI の生成 |
| `show_in_menu` | `bool\|string` | `$show_ui` | 管理メニューへの表示 |
| `show_in_nav_menus` | `bool` | `$public` | ナビゲーションメニューでの利用可否 |
| `show_in_admin_bar` | `bool` | `$show_in_menu` | 管理バーでの表示 |
| `show_in_rest` | `bool` | `false` | REST API での公開（ブロックエディタ対応に必要） |
| `rest_base` | `string` | `$post_type` | REST エンドポイントのベース |
| `rest_namespace` | `string` | `'wp/v2'` | REST 名前空間 |
| `rest_controller_class` | `string` | `'WP_REST_Posts_Controller'` | REST コントローラークラス |
| `menu_position` | `int\|null` | `null` | 管理メニューの表示位置 |
| `menu_icon` | `string\|null` | `null` | メニューアイコン（Dashicons クラスまたは SVG） |
| `capability_type` | `string\|array` | `'post'` | 権限のベース文字列 |
| `capabilities` | `array` | `[]` | 権限のカスタムマッピング |
| `map_meta_cap` | `bool` | `false` | メタ権限マッピングの使用 |
| `supports` | `array\|false` | `['title', 'editor']` | サポートする機能 |
| `taxonomies` | `array` | `[]` | 関連付けるタクソノミー |
| `has_archive` | `bool\|string` | `false` | アーカイブページの有効化 |
| `rewrite` | `bool\|array` | `true` | URL リライトルール |
| `query_var` | `string\|bool` | `true` | クエリ変数名 |
| `can_export` | `bool` | `true` | エクスポート可能か |
| `delete_with_user` | `bool\|null` | `null` | ユーザー削除時の投稿処理 |
| `template` | `array` | `[]` | ブロックエディタのデフォルトテンプレート |
| `template_lock` | `string\|false` | `false` | テンプレートのロック（`'all'`, `'insert'`, `false`） |

#### `supports` で指定できる機能

| 機能名 | 説明 |
|---|---|
| `'title'` | タイトル欄 |
| `'editor'` | コンテンツエディタ |
| `'author'` | 投稿者メタボックス |
| `'thumbnail'` | アイキャッチ画像 |
| `'excerpt'` | 抜粋欄 |
| `'trackbacks'` | トラックバック送信 |
| `'custom-fields'` | カスタムフィールドメタボックス |
| `'comments'` | コメント対応 |
| `'revisions'` | リビジョン管理 |
| `'page-attributes'` | ページ属性（メニュー順、親ページ） |
| `'post-formats'` | 投稿フォーマット |

### 投稿 CRUD API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_insert_post()` | `(array $postarr, bool $wp_error = false, bool $fire_after_hooks = true): int\|WP_Error` | 投稿を挿入または更新 |
| `wp_update_post()` | `(array\|object $postarr = [], bool $wp_error = false, bool $fire_after_hooks = true): int\|WP_Error` | 投稿を更新 |
| `wp_delete_post()` | `(int $post_id = 0, bool $force_delete = false): WP_Post\|false\|null` | 投稿を削除（デフォルトはゴミ箱へ） |
| `wp_trash_post()` | `(int $post_id = 0): WP_Post\|false\|null` | 投稿をゴミ箱に移動 |
| `wp_untrash_post()` | `(int $post_id = 0): WP_Post\|false\|null` | 投稿をゴミ箱から復元 |
| `get_post()` | `(int\|WP_Post\|null $post = null, string $output = OBJECT, string $filter = 'raw'): WP_Post\|array\|null` | 投稿オブジェクトを取得 |
| `get_posts()` | `(array $args = null): WP_Post[]\|int[]` | 条件に一致する投稿を取得 |
| `get_post_type()` | `(int\|WP_Post\|null $post = null): string\|false` | 投稿タイプを取得 |
| `get_post_status()` | `(int\|WP_Post $post = null): string\|false` | 投稿ステータスを取得 |

#### `wp_insert_post()` の `$postarr` パラメータ

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `ID` | `int` | `0` | 投稿 ID（0 で新規挿入、指定で更新） |
| `post_author` | `int` | 現在のユーザー ID | 投稿者 ID |
| `post_date` | `string` | 現在日時 | 投稿日時 |
| `post_date_gmt` | `string` | `$post_date` から算出 | 投稿日時（GMT） |
| `post_content` | `string` | `''` | 投稿本文 |
| `post_content_filtered` | `string` | `''` | フィルタ済みコンテンツ |
| `post_title` | `string` | `''` | タイトル |
| `post_excerpt` | `string` | `''` | 抜粋 |
| `post_status` | `string` | `'draft'` | ステータス |
| `post_type` | `string` | `'post'` | 投稿タイプ |
| `post_name` | `string` | サニタイズ済みタイトル | スラッグ |
| `post_parent` | `int` | `0` | 親投稿 ID |
| `menu_order` | `int` | `0` | メニュー順 |
| `post_password` | `string` | `''` | パスワード |
| `comment_status` | `string` | デフォルト設定 | コメント状態 |
| `ping_status` | `string` | デフォルト設定 | ピンバック状態 |
| `post_category` | `array` | 未分類 | カテゴリ ID の配列 |
| `tags_input` | `array` | `[]` | タグの配列 |
| `tax_input` | `array` | `[]` | タクソノミーと用語のマッピング |
| `meta_input` | `array` | `[]` | メタデータのキー・値ペア |

### 投稿メタ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_post_meta()` | `(int $post_id, string $key = '', bool $single = false): mixed` | 投稿メタデータを取得 |
| `add_post_meta()` | `(int $post_id, string $meta_key, mixed $meta_value, bool $unique = false): int\|false` | 投稿メタデータを追加 |
| `update_post_meta()` | `(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int\|bool` | 投稿メタデータを更新 |
| `delete_post_meta()` | `(int $post_id, string $meta_key, mixed $meta_value = ''): bool` | 投稿メタデータを削除 |

### WP_Query メソッド

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `__construct()` | `(string\|array $query = '')` | クエリの初期化と実行 |
| `query()` | `(string\|array $query): WP_Post[]` | クエリを実行して結果を返す |
| `get_posts()` | `(): WP_Post[]` | クエリ実行の本体 |
| `have_posts()` | `(): bool` | ループ内に投稿が残っているか |
| `the_post()` | `(): void` | 次の投稿に進み、`$post` グローバルを設定 |
| `rewind_posts()` | `(): void` | ループ位置をリセット |
| `set()` | `(string $query_var, mixed $value): void` | クエリ変数を設定 |
| `get()` | `(string $query_var, mixed $default = ''): mixed` | クエリ変数を取得 |

### WP_Query クエリパラメータ

#### 投稿タイプ・ステータス

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `post_type` | `string\|array` | `'post'` | 投稿タイプ（`'any'` で全タイプ） |
| `post_status` | `string\|array` | `'publish'` | ステータス（`'any'` で全ステータス） |

#### ID 指定

| パラメータ | 型 | 説明 |
|---|---|---|
| `p` | `int` | 投稿 ID で指定 |
| `name` | `string` | スラッグで指定 |
| `post__in` | `int[]` | 指定 ID の投稿を含む |
| `post__not_in` | `int[]` | 指定 ID の投稿を除外 |
| `post_parent` | `int` | 親投稿 ID で絞り込み |
| `post_parent__in` | `int[]` | 指定親 ID の投稿を含む |
| `post_parent__not_in` | `int[]` | 指定親 ID の投稿を除外 |
| `page_id` | `int` | ページ ID で指定 |
| `pagename` | `string` | ページスラッグで指定 |

#### 投稿者

| パラメータ | 型 | 説明 |
|---|---|---|
| `author` | `int` | 投稿者 ID |
| `author_name` | `string` | 投稿者のニックネーム |
| `author__in` | `int[]` | 指定投稿者を含む |
| `author__not_in` | `int[]` | 指定投稿者を除外 |

#### カテゴリ・タグ

| パラメータ | 型 | 説明 |
|---|---|---|
| `cat` | `int` | カテゴリ ID（負値で除外） |
| `category_name` | `string` | カテゴリスラッグ |
| `category__and` | `int[]` | すべてのカテゴリに属する投稿 |
| `category__in` | `int[]` | いずれかのカテゴリに属する投稿 |
| `category__not_in` | `int[]` | 指定カテゴリを除外 |
| `tag` | `string` | タグスラッグ |
| `tag_id` | `int` | タグ ID |
| `tag__and` | `int[]` | すべてのタグを持つ投稿 |
| `tag__in` | `int[]` | いずれかのタグを持つ投稿 |
| `tag__not_in` | `int[]` | 指定タグを除外 |
| `tag_slug__and` | `string[]` | すべてのタグスラッグを持つ投稿 |
| `tag_slug__in` | `string[]` | いずれかのタグスラッグを持つ投稿 |

#### タクソノミークエリ（`tax_query`）

```php
'tax_query' => [
    'relation' => 'AND',               // 'AND' | 'OR'（デフォルト: 'AND'）
    [
        'taxonomy'         => 'category',
        'field'            => 'slug',   // 'term_id' | 'name' | 'slug' | 'term_taxonomy_id'
        'terms'            => ['news', 'events'],
        'operator'         => 'IN',     // 'IN' | 'NOT IN' | 'AND' | 'EXISTS' | 'NOT EXISTS'
        'include_children' => true,     // 階層タクソノミーで子用語を含むか
    ],
    [
        'taxonomy' => 'post_tag',
        'field'    => 'term_id',
        'terms'    => [42, 43],
        'operator' => 'AND',
    ],
]
```

ネストされた `tax_query` をサポートし、`relation` と句の配列を再帰的に組み合わせることが可能です。

#### メタクエリ（`meta_query`）

```php
'meta_query' => [
    'relation' => 'OR',                // 'AND' | 'OR'
    'price_clause' => [                // 名前付き句（orderby で使用可能）
        'key'     => '_price',
        'value'   => 100,
        'compare' => '>=',            // '=' | '!=' | '>' | '>=' | '<' | '<=' |
                                      // 'LIKE' | 'NOT LIKE' | 'IN' | 'NOT IN' |
                                      // 'BETWEEN' | 'NOT BETWEEN' | 'EXISTS' | 'NOT EXISTS'
        'type'    => 'NUMERIC',       // 'NUMERIC' | 'DECIMAL' | 'SIGNED' | 'UNSIGNED' |
                                      // 'CHAR' | 'BINARY' | 'DATE' | 'DATETIME' | 'TIME'
    ],
    [
        'key'     => '_featured',
        'compare' => 'EXISTS',
    ],
]
```

#### 日付クエリ（`date_query`）

```php
'date_query' => [
    'relation' => 'AND',
    [
        'column'    => 'post_date',    // 'post_date' | 'post_date_gmt' | 'post_modified' | 'post_modified_gmt'
        'after'     => '2024-01-01',   // 文字列または ['year' => 2024, 'month' => 1, 'day' => 1]
        'before'    => '2024-12-31',
        'inclusive' => true,
    ],
    [
        'column'  => 'post_date',
        'year'    => 2024,
        'monthnum' => [6, 7, 8],       // 配列で複数指定可
    ],
]
```

#### 検索

| パラメータ | 型 | 説明 |
|---|---|---|
| `s` | `string` | 検索キーワード |
| `search_columns` | `array` | 検索対象カラム（`'post_title'`, `'post_excerpt'`, `'post_content'`） |
| `exact` | `bool` | 完全一致検索 |
| `sentence` | `bool` | フレーズ検索 |

#### ページネーション

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `posts_per_page` | `int` | 管理画面設定値 | 1 ページあたりの投稿数（`-1` で全件） |
| `nopaging` | `bool` | `false` | ページネーション無効化 |
| `paged` | `int` | `1` | ページ番号 |
| `offset` | `int` | `0` | スキップする投稿数 |

#### ソート

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `orderby` | `string\|array` | `'date'` | ソートフィールド |
| `order` | `string` | `'DESC'` | ソート方向（`'ASC'` \| `'DESC'`） |

**`orderby` に指定できる値:**

| 値 | 説明 |
|---|---|
| `'none'` | ソートなし |
| `'ID'` | 投稿 ID |
| `'author'` | 投稿者 ID |
| `'title'` | タイトル |
| `'name'` | スラッグ |
| `'type'` | 投稿タイプ |
| `'date'` | 投稿日時 |
| `'modified'` | 更新日時 |
| `'parent'` | 親投稿 ID |
| `'rand'` | ランダム |
| `'comment_count'` | コメント数 |
| `'relevance'` | 検索時の関連度 |
| `'menu_order'` | メニュー順 |
| `'meta_value'` | メタ値（文字列ソート） |
| `'meta_value_num'` | メタ値（数値ソート） |
| `'post__in'` | `post__in` の指定順 |
| `'post_name__in'` | `post_name__in` の指定順 |
| `'post_parent__in'` | `post_parent__in` の指定順 |
| 名前付き meta_query 句 | `meta_query` で定義した句名 |

#### フィールドとキャッシュ

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `fields` | `string` | `''` | `'ids'` で ID のみ、`'id=>parent'` で ID と親 ID のみ取得 |
| `no_found_rows` | `bool` | `false` | `SQL_CALC_FOUND_ROWS` を省略（ページネーション不要時にパフォーマンス向上） |
| `cache_results` | `bool` | `true` | クエリ結果をキャッシュ |
| `update_post_meta_cache` | `bool` | `true` | メタデータキャッシュを更新 |
| `update_post_term_cache` | `bool` | `true` | 用語キャッシュを更新 |
| `lazy_load_term_meta` | `bool` | `true` | 用語メタの遅延読み込み |

## 4. 実行フロー

### `register_post_type()` の実行フロー

```
register_post_type('product', $args)
│
├── バリデーション
│   ├── $post_type が 20 文字以内か確認
│   ├── 予約済みスラッグとの衝突チェック
│   └── 既存登録の上書きチェック
│
├── new WP_Post_Type('product', $args)
│   ├── set_props($args)            // プロパティの設定（デフォルト値のマージ）
│   └── 各プロパティの継承処理
│       ├── show_ui ← public
│       ├── show_in_menu ← show_ui
│       ├── show_in_nav_menus ← public
│       ├── publicly_queryable ← public
│       └── exclude_from_search ← !public
│
├── $wp_post_types['product'] = $post_type_object
│
├── $post_type_object->add_supports()     // supports 配列を処理
├── $post_type_object->add_rewrite_rules() // リライトルールを追加
├── $post_type_object->register_taxonomies() // タクソノミーを関連付け
├── $post_type_object->add_hooks()        // future_{post_type} フックを登録
│
├── do_action('registered_post_type', 'product', $post_type_object)
│
└── return $post_type_object
```

### `wp_insert_post()` の実行フロー

```
wp_insert_post($postarr)
│
├── データサニタイズ
│   ├── post_status のバリデーション
│   ├── post_type のバリデーション
│   ├── post_date の検証と修正
│   ├── post_name（スラッグ）の生成/サニタイズ
│   │   └── wp_unique_post_slug() で一意性を保証
│   └── コンテンツの kses フィルタリング
│
├── apply_filters('wp_insert_post_data', $data, $postarr, ...)  // データをフィルタリング
│
├── $postarr['ID'] > 0 の場合（更新）
│   ├── $wpdb->update($wpdb->posts, $data, ['ID' => $post_id])
│   └── clean_post_cache($post_id)
│
├── $postarr['ID'] == 0 の場合（新規挿入）
│   └── $wpdb->insert($wpdb->posts, $data)
│       └── $post_id = $wpdb->insert_id
│
├── タクソノミーの処理
│   ├── post_category → wp_set_post_categories()
│   ├── tags_input → wp_set_post_tags()
│   └── tax_input → wp_set_object_terms()
│
├── メタデータの処理
│   └── meta_input → update_post_meta() (各キー)
│
├── do_action('save_post_{$post_type}', $post_id, $post, $update)
├── do_action('save_post', $post_id, $post, $update)
├── do_action('wp_insert_post', $post_id, $post, $update)
│
└── return $post_id
```

### WP_Query の実行フロー

```
new WP_Query(['post_type' => 'product', 'posts_per_page' => 10])
│
├── $this->query($query_vars)
│   │
│   ├── $this->init()               // プロパティ初期化
│   ├── $this->parse_query($query_vars)
│   │   ├── クエリ変数のパースとデフォルト設定
│   │   ├── is_* コンディショナルフラグの設定
│   │   └── 'post_type', 'post_status' 等の正規化
│   │
│   └── $this->get_posts()
│       │
│       ├── do_action_ref_array('pre_get_posts', [&$this])  // クエリ変更可能
│       │
│       ├── SQL 構築フェーズ
│       │   ├── WHERE 句の構築
│       │   │   ├── post_type/post_status フィルタ
│       │   │   ├── 投稿者フィルタ
│       │   │   ├── 検索フィルタ（s パラメータ）
│       │   │   └── 日付フィルタ
│       │   │
│       │   ├── tax_query → WP_Tax_Query でタクソノミー JOIN/WHERE 生成
│       │   ├── meta_query → WP_Meta_Query でメタ JOIN/WHERE 生成
│       │   ├── date_query → WP_Date_Query で日付 WHERE 生成
│       │   │
│       │   ├── apply_filters('posts_where', $where)
│       │   ├── apply_filters('posts_join', $join)
│       │   ├── apply_filters('posts_groupby', $groupby)
│       │   ├── apply_filters('posts_orderby', $orderby)
│       │   ├── apply_filters('posts_distinct', $distinct)
│       │   ├── apply_filters('post_limits', $limits)
│       │   ├── apply_filters('posts_fields', $fields)
│       │   ├── apply_filters('posts_clauses', $clauses)      // 全句をまとめてフィルタ
│       │   │
│       │   └── SQL 文の組み立て
│       │       └── apply_filters('posts_request', $request)   // 最終 SQL をフィルタ
│       │
│       ├── apply_filters('posts_pre_query', null, $this)      // ショートサーキット可能
│       │
│       ├── $wpdb->get_results($request) で実行
│       │
│       ├── 結果の後処理
│       │   ├── apply_filters('posts_results', $posts, $this)
│       │   ├── スティッキー投稿の処理（is_home 時）
│       │   ├── apply_filters('the_posts', $posts, $this)
│       │   └── キャッシュの更新
│       │       ├── update_post_caches($posts)
│       │       ├── update_postmeta_cache()
│       │       └── update_object_term_cache()
│       │
│       └── return $this->posts
│
└── The Loop で使用
    while ($query->have_posts()):
        $query->the_post();
        ├── $this->current_post++
        ├── $this->post = $this->posts[$this->current_post]
        ├── setup_postdata($this->post)
        │   └── $GLOBALS['post'] = $this->post
        └── // テンプレート内で the_title(), the_content() 等を使用
    endwhile;
    wp_reset_postdata();
```

## 5. 投稿ステータス

### 組み込みステータス

| ステータス | スラッグ | 説明 |
|---|---|---|
| 公開 | `publish` | 公開済みの投稿 |
| 未来 | `future` | 予約投稿（公開日が未来） |
| 下書き | `draft` | 下書き |
| レビュー待ち | `pending` | レビュー待ち |
| 非公開 | `private` | 非公開（権限のあるユーザーのみ閲覧可） |
| ゴミ箱 | `trash` | ゴミ箱（30日後に自動削除） |
| 自動下書き | `auto-draft` | 自動保存の下書き |
| 継承 | `inherit` | リビジョン・添付ファイルが親投稿のステータスを継承 |

### カスタムステータスの登録

```php
register_post_status(string $post_status, array $args = []): object
```

## 6. フック一覧

### 投稿タイプ登録

| フック名 | 種類 | シグネチャ | 説明 |
|---|---|---|---|
| `registered_post_type` | Action | `(string $post_type, WP_Post_Type $post_type_object)` | 投稿タイプ登録直後 |
| `unregistered_post_type` | Action | `(string $post_type)` | 投稿タイプ登録解除直後 |
| `register_post_type_args` | Filter | `(array $args, string $post_type): array` | 登録引数をフィルタリング |

### 投稿の保存

| フック名 | 種類 | シグネチャ | 説明 |
|---|---|---|---|
| `wp_insert_post_data` | Filter | `(array $data, array $postarr, array $unsanitized, bool $update): array` | DB 挿入前のデータをフィルタリング |
| `wp_insert_post_empty_content` | Filter | `(bool $maybe_empty, array $postarr): bool` | 空コンテンツの投稿を許可するか |
| `save_post_{$post_type}` | Action | `(int $post_id, WP_Post $post, bool $update)` | 特定投稿タイプの保存後 |
| `save_post` | Action | `(int $post_id, WP_Post $post, bool $update)` | 投稿保存後（全投稿タイプ） |
| `wp_insert_post` | Action | `(int $post_id, WP_Post $post, bool $update)` | 投稿挿入後（用語・メタ処理後） |
| `edit_post` | Action | `(int $post_id, WP_Post $post)` | 投稿更新時 |
| `pre_post_update` | Action | `(int $post_id, array $data)` | 投稿更新前 |

### 投稿の削除

| フック名 | 種類 | シグネチャ | 説明 |
|---|---|---|---|
| `wp_trash_post` | Action | `(int $post_id)` | ゴミ箱移動前 |
| `trashed_post` | Action | `(int $post_id)` | ゴミ箱移動後 |
| `before_delete_post` | Action | `(int $post_id, WP_Post $post)` | 完全削除前 |
| `delete_post` | Action | `(int $post_id, WP_Post $post)` | 完全削除後（メタ・用語削除後） |
| `deleted_post` | Action | `(int $post_id, WP_Post $post)` | 完全削除完了後 |

### WP_Query フック

| フック名 | 種類 | シグネチャ | 説明 |
|---|---|---|---|
| `pre_get_posts` | Action | `(WP_Query &$query)` | クエリ変数のパース後、SQL 生成前 |
| `posts_where` | Filter | `(string $where, WP_Query $query): string` | WHERE 句 |
| `posts_join` | Filter | `(string $join, WP_Query $query): string` | JOIN 句 |
| `posts_groupby` | Filter | `(string $groupby, WP_Query $query): string` | GROUP BY 句 |
| `posts_orderby` | Filter | `(string $orderby, WP_Query $query): string` | ORDER BY 句 |
| `posts_distinct` | Filter | `(string $distinct, WP_Query $query): string` | DISTINCT 句 |
| `post_limits` | Filter | `(string $limits, WP_Query $query): string` | LIMIT 句 |
| `posts_fields` | Filter | `(string $fields, WP_Query $query): string` | SELECT フィールド |
| `posts_clauses` | Filter | `(array $clauses, WP_Query $query): array` | 全 SQL 句をまとめてフィルタ |
| `posts_request` | Filter | `(string $request, WP_Query $query): string` | 最終 SQL 文 |
| `posts_pre_query` | Filter | `(WP_Post[]\|int[]\|null $posts, WP_Query $query): mixed` | クエリのショートサーキット |
| `posts_results` | Filter | `(WP_Post[] $posts, WP_Query $query): WP_Post[]` | クエリ結果 |
| `the_posts` | Filter | `(WP_Post[] $posts, WP_Query $query): WP_Post[]` | スティッキー処理後の結果 |
| `found_posts` | Filter | `(int $found_posts, WP_Query $query): int` | 見つかった投稿の総数 |

### 投稿コンテンツフィルター

| フック名 | 種類 | シグネチャ | 説明 |
|---|---|---|---|
| `the_title` | Filter | `(string $title, int $post_id): string` | 投稿タイトル |
| `the_content` | Filter | `(string $content): string` | 投稿コンテンツ |
| `the_excerpt` | Filter | `(string $excerpt): string` | 投稿の抜粋 |
| `wp_trim_excerpt` | Filter | `(string $text, string $raw_excerpt): string` | 自動生成された抜粋 |

### 投稿ステータス遷移

| フック名 | 種類 | シグネチャ | 説明 |
|---|---|---|---|
| `transition_post_status` | Action | `(string $new_status, string $old_status, WP_Post $post)` | ステータス変更時 |
| `{$old_status}_to_{$new_status}` | Action | `(WP_Post $post)` | 特定のステータス遷移時 |
| `{$new_status}_{$post_type}` | Action | `(int $post_id, WP_Post $post)` | 特定ステータス・投稿タイプの組み合わせ |
