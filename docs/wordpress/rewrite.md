# WordPress Rewrite API 仕様

## 1. 概要

WordPress Rewrite API は、URL をクエリ変数にマッピングするルーティングシステムです。`WP_Rewrite` クラスが正規表現ベースのリライトルールを管理し、Apache の `mod_rewrite` や Nginx のリライト設定と連携してきれいな URL（パーマリンク）を実現します。

| クラス / ファイル | 説明 |
|---|---|
| `WP_Rewrite` | リライトルールの生成・管理を担当するコアクラス |
| `rewrite.php` | ヘルパー関数群（`add_rewrite_rule()`, `flush_rewrite_rules()` 等） |
| `class-wp.php` | `WP::parse_request()` でリライトルールを実際にマッチさせてクエリ変数に変換 |

### グローバル変数

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_rewrite` | `WP_Rewrite` | Rewrite API のシングルトンインスタンス |

## 2. データ構造

### WP_Rewrite クラス

```php
class WP_Rewrite {
    // パーマリンク構造
    public $permalink_structure;          // 投稿のパーマリンク構造（例: '/%year%/%monthnum%/%postname%/'）
    public $use_trailing_slashes;         // 末尾スラッシュの使用
    public $author_base       = 'author'; // 著者ページのベース
    public $author_structure;             // 著者ページの構造
    public $date_structure;               // 日付アーカイブの構造
    public $page_structure;               // 固定ページの構造
    public $search_base       = 'search'; // 検索ページのベース
    public $search_structure;             // 検索ページの構造
    public $comments_base     = 'comments'; // コメントフィードのベース
    public $pagination_base   = 'page';   // ページネーションのベース
    public $comments_pagination_base = 'comment-page'; // コメントページネーションのベース
    public $feed_base         = 'feed';   // フィードのベース
    public $feed_structure;               // フィードの構造
    public $front;                        // パーマリンク構造のフロント部分（スラッシュまで）

    // ルール管理
    public $rules;                        // 現在のリライトルール配列
    public $extra_rules       = [];       // add_rewrite_rule() で追加されたルール
    public $extra_rules_top   = [];       // 先頭に追加されたルール
    public $non_wp_rules      = [];       // 外部リダイレクトルール
    public $extra_permastructs = [];      // カスタムパーマリンク構造
    public $endpoints         = [];       // エンドポイント

    // 内部状態
    public $use_verbose_rules  = false;   // 詳細ルールモード（IIS 等で使用）
    public $use_verbose_page_rules = true; // 固定ページの詳細ルール
    private $rewritecode      = [];       // リライトタグ配列
    private $rewritereplace   = [];       // リライトタグ → 正規表現マッピング
    private $queryreplace     = [];       // リライトタグ → クエリ変数マッピング
}
```

### リライトルールの構造

リライトルールは正規表現をキー、クエリ文字列を値とする連想配列です:

```php
$rules = [
    // 投稿ルール
    '([0-9]{4})/([0-9]{1,2})/([^/]+)(?:/([0-9]+))?/?$'
        => 'index.php?year=$matches[1]&monthnum=$matches[2]&name=$matches[3]&page=$matches[4]',

    // カテゴリールール
    'category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$'
        => 'index.php?category_name=$matches[1]&feed=$matches[2]',

    // 固定ページルール
    '(.?.+?)(?:/([0-9]+))?/?$'
        => 'index.php?pagename=$matches[1]&page=$matches[2]',
];
```

### リライトタグ

リライトタグはパーマリンク構造内のプレースホルダーで、正規表現とクエリ変数に変換されます:

| タグ | 正規表現 | クエリ変数 |
|---|---|---|
| `%year%` | `([0-9]{4})` | `year=` |
| `%monthnum%` | `([0-9]{1,2})` | `monthnum=` |
| `%day%` | `([0-9]{1,2})` | `day=` |
| `%hour%` | `([0-9]{1,2})` | `hour=` |
| `%minute%` | `([0-9]{1,2})` | `minute=` |
| `%second%` | `([0-9]{1,2})` | `second=` |
| `%postname%` | `([^/]+)` | `name=` |
| `%post_id%` | `([0-9]+)` | `p=` |
| `%category%` | `(.+?)` | `category_name=` |
| `%tag%` | `([^/]+)` | `tag=` |
| `%author%` | `([^/]+)` | `author_name=` |
| `%pagename%` | `(.?.+?)` | `pagename=` |
| `%search%` | `(.+)` | `s=` |

### パーマリンク構造オプション

`extra_permastructs` に格納されるカスタムパーマリンク構造の設定:

```php
$permastructs['post_tag'] = [
    'struct'      => '/tag/%tag%',           // パーマリンク構造
    'with_front'  => true,                    // $front を先頭に付けるか
    'ep_mask'     => EP_TAGS,                 // エンドポイントマスク
    'paged'       => true,                    // ページネーション対応
    'feed'        => true,                    // フィード対応
    'forcomments' => false,                   // コメントフィード対応
    'walk_dirs'   => true,                    // ディレクトリ単位でルール生成
    'endpoints'   => true,                    // エンドポイント対応
];
```

## 3. API リファレンス

### ルール登録

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_rewrite_rule()` | `(string $regex, string\|array $query, string $after = 'bottom')` | リライトルールを追加 |
| `add_rewrite_tag()` | `(string $tag, string $regex, string $query = '')` | リライトタグを追加 |
| `add_rewrite_endpoint()` | `(string $name, int $places, string\|bool $query_var = true)` | エンドポイントを追加 |
| `add_permastruct()` | `(string $name, string $struct, array $args = [])` | パーマリンク構造を追加 |

`add_rewrite_rule()` の `$after` パラメータ:

| 値 | 説明 |
|---|---|
| `'top'` | `$extra_rules_top` に追加。WordPress の既存ルールより先にマッチ |
| `'bottom'` | `$extra_rules` に追加。既存ルールの後にマッチ |

```php
// カスタムルールの追加
add_rewrite_rule(
    '^products/([^/]+)/?$',
    'index.php?product=$matches[1]',
    'top'
);

// カスタムリライトタグの追加
add_rewrite_tag('%product%', '([^/]+)', 'product=');

// エンドポイントの追加（例: /my-post/json/ でアクセス可能）
add_rewrite_endpoint('json', EP_PERMALINK);

// カスタムパーマリンク構造
add_permastruct('product', '/products/%product%', [
    'with_front' => false,
    'paged'      => false,
]);
```

### ルール管理

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `flush_rewrite_rules()` | `(bool $hard = true)` | リライトルールを再生成。`$hard = true` で `.htaccess` も更新 |
| `got_url_rewrite()` | `(): bool` | URL リライトが利用可能か判定 |

### パーマリンク生成

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_Rewrite::get_date_permastruct()` | `(): string\|false` | 日付アーカイブのパーマリンク構造を取得 |
| `WP_Rewrite::get_year_permastruct()` | `(): string\|false` | 年アーカイブの構造を取得 |
| `WP_Rewrite::get_month_permastruct()` | `(): string\|false` | 月アーカイブの構造を取得 |
| `WP_Rewrite::get_day_permastruct()` | `(): string\|false` | 日アーカイブの構造を取得 |
| `WP_Rewrite::get_author_permastruct()` | `(): string\|false` | 著者アーカイブの構造を取得 |
| `WP_Rewrite::get_search_permastruct()` | `(): string\|false` | 検索ページの構造を取得 |
| `WP_Rewrite::get_page_permastruct()` | `(): string\|false` | 固定ページの構造を取得 |
| `WP_Rewrite::get_feed_permastruct()` | `(): string\|false` | フィードの構造を取得 |
| `WP_Rewrite::get_comment_feed_permastruct()` | `(): string\|false` | コメントフィードの構造を取得 |
| `WP_Rewrite::get_extra_permastruct()` | `(string $name): string\|false` | カスタムパーマリンク構造を取得 |

### ルール生成

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_Rewrite::generate_rewrite_rules()` | `(string $permalink_structure, int $ep_mask = EP_NONE, bool $paged = true, bool $feed = true, bool $forcomments = false, bool $walk_dirs = true, bool $endpoints = true): array` | パーマリンク構造からルールを生成 |
| `WP_Rewrite::wp_rewrite_rules()` | `(): array` | 全リライトルールを生成して返す |
| `WP_Rewrite::rewrite_rules()` | `(): array` | `wp_rewrite_rules()` のラッパー（非推奨） |

### クエリ変数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_query_var()` | `(string $qv)` | パブリッククエリ変数を追加 |

### URL 操作ヘルパー

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `url_to_postid()` | `(string $url): int` | URL から投稿 ID を逆引き |
| `get_permalink()` | `(int\|WP_Post $post = 0, bool $leavename = false): string\|false` | 投稿のパーマリンクを取得 |
| `get_post_permalink()` | `(int\|WP_Post $post = 0, bool $leavename = false, bool $sample = false): string\|false` | カスタム投稿タイプのパーマリンクを取得 |
| `get_page_link()` | `(int\|WP_Post $post = false, bool $leavename = false, bool $sample = false): string` | 固定ページのリンクを取得 |
| `get_term_link()` | `(WP_Term\|int\|string $term, string $taxonomy = ''): string\|WP_Error` | ターム（カテゴリー/タグ等）のリンクを取得 |
| `get_author_posts_url()` | `(int $author_id, string $author_nicename = ''): string` | 著者ページの URL を取得 |
| `get_year_link()` | `(int\|false $year): string` | 年アーカイブの URL を取得 |
| `get_month_link()` | `(int\|false $year, int\|false $month): string` | 月アーカイブの URL を取得 |
| `get_day_link()` | `(int\|false $year, int\|false $month, int\|false $day): string` | 日アーカイブの URL を取得 |
| `get_feed_link()` | `(string $feed = ''): string` | フィードの URL を取得 |
| `get_search_link()` | `(string $query = ''): string` | 検索ページの URL を取得 |
| `trailingslashit()` | `(string $value): string` | 末尾にスラッシュを追加 |
| `untrailingslashit()` | `(string $value): string` | 末尾のスラッシュを除去 |
| `user_trailingslashit()` | `(string $url, string $type = ''): string` | パーマリンク設定に基づき末尾スラッシュを調整 |

## 4. 実行フロー

### リライトルール生成フロー

```
WP_Rewrite::wp_rewrite_rules()
│
├── permalink_structure が空なら空配列を返す
│
├── 各パーマリンク構造からルール生成
│   ├── 投稿ルール（permalink_structure）
│   ├── 日付アーカイブルール（date_structure）
│   ├── 著者ルール（author_structure）
│   ├── 検索ルール（search_structure）
│   ├── フィードルール（feed_structure）
│   ├── 固定ページルール（page_structure）
│   └── extra_permastructs のルール（カスタム投稿/タクソノミー等）
│       └── generate_rewrite_rules() で各構造をルール配列に変換
│
├── ルールのマージ
│   ├── $extra_rules_top（先頭追加ルール）
│   ├── 生成されたルール群
│   ├── $extra_rules（末尾追加ルール）
│   └── 固定ページルール（最後）
│
├── apply_filters('rewrite_rules_array', $rules)
│
└── $this->rules = $rules
```

### generate_rewrite_rules() の詳細

```
generate_rewrite_rules($permastruct, ...)
│
├── パーマリンク構造をセグメント（/で分割）に分解
│
├── walk_dirs が true の場合
│   └── 各ディレクトリ深度でルールを生成
│       例: /%year%/%monthnum%/%postname%/ →
│           ルール1: year のみ
│           ルール2: year + monthnum
│           ルール3: year + monthnum + postname
│
├── 各リライトタグを正規表現に置換
│   └── $rewritecode → $rewritereplace マッピングを使用
│
├── クエリ文字列を構築
│   └── $matches[N] を対応するクエリ変数にマッピング
│
├── エンドポイント用ルールを追加（endpoints = true の場合）
│   └── 各エンドポイントに対してルールを生成
│
├── フィードルールを追加（feed = true の場合）
│
├── ページネーションルールを追加（paged = true の場合）
│
└── ルール配列を返す
```

### URL マッチングフロー（WP::parse_request）

```
WP::parse_request()
│
├── URL パスを取得
│   ├── $_SERVER['PATH_INFO'] or
│   └── $_SERVER['REQUEST_URI'] からパスを抽出
│
├── パーマリンクが有効か確認
│   └── 無効なら $_GET からクエリ変数を取得して終了
│
├── $wp_rewrite->wp_rewrite_rules() でルール取得
│
├── リライトルールを順番に走査
│   ├── preg_match($regex, $request_path, $matches)
│   ├── マッチしたらクエリ文字列を構築
│   │   └── $matches[N] をクエリ変数の値に置換
│   └── 最初にマッチしたルールで停止
│
├── マッチしなかった場合
│   └── 404 として処理
│
├── クエリ変数を解析
│   ├── パブリッククエリ変数のみ許可
│   │   └── $wp->public_query_vars + query_vars フィルター
│   └── プライベートクエリ変数はログインユーザーのみ許可
│
├── do_action_ref_array('parse_request', [&$this])
│
└── $wp->query_vars にセット → WP_Query で使用
```

### flush_rewrite_rules() のフロー

```
flush_rewrite_rules($hard = true)
│
├── delete_option('rewrite_rules')  // DB キャッシュを削除
│
├── $wp_rewrite->wp_rewrite_rules() // ルール再生成
│
├── update_option('rewrite_rules', $rules) // DB に保存
│
├── $hard が true の場合
│   └── $wp_rewrite->mod_rewrite_rules() or iis7_url_rewrite_rules()
│       └── .htaccess / web.config を更新
│
└── do_action('generate_rewrite_rules', $wp_rewrite)
```

## 5. エンドポイント

### EP マスク定数

エンドポイントマスクは、エンドポイントがどのタイプの URL に追加されるかを制御するビットマスクです:

| 定数 | 値 | 説明 |
|---|---|---|
| `EP_NONE` | `0` | なし |
| `EP_PERMALINK` | `1` | 投稿パーマリンク |
| `EP_ATTACHMENT` | `2` | 添付ファイル |
| `EP_DATE` | `4` | 日付アーカイブ |
| `EP_YEAR` | `8` | 年アーカイブ |
| `EP_MONTH` | `16` | 月アーカイブ |
| `EP_DAY` | `32` | 日アーカイブ |
| `EP_ROOT` | `64` | ルート |
| `EP_COMMENTS` | `128` | コメント |
| `EP_SEARCH` | `256` | 検索 |
| `EP_CATEGORIES` | `512` | カテゴリー |
| `EP_TAGS` | `1024` | タグ |
| `EP_AUTHORS` | `2048` | 著者 |
| `EP_PAGES` | `4096` | 固定ページ |
| `EP_ALL_ARCHIVES` | `EP_DATE \| EP_YEAR \| ...` | 全アーカイブ |
| `EP_ALL` | `EP_PERMALINK \| EP_ALL_ARCHIVES \| ...` | 全て |

## 6. フック一覧

### Action

| フック名 | 引数 | 説明 |
|---|---|---|
| `generate_rewrite_rules` | `(WP_Rewrite $wp_rewrite)` | リライトルール生成後。ルールのカスタマイズに使用 |
| `parse_request` | `(WP &$wp)` | リクエスト解析後。クエリ変数の追加・変更に使用 |

### Filter

| フック名 | 引数 | 説明 |
|---|---|---|
| `rewrite_rules_array` | `(array $rules)` | 全リライトルール配列をフィルター |
| `query_vars` | `(array $public_query_vars)` | パブリッククエリ変数の配列をフィルター |
| `post_rewrite_rules` | `(array $rules)` | 投稿のリライトルールをフィルター |
| `date_rewrite_rules` | `(array $rules)` | 日付アーカイブのリライトルールをフィルター |
| `root_rewrite_rules` | `(array $rules)` | ルートのリライトルールをフィルター |
| `comments_rewrite_rules` | `(array $rules)` | コメントのリライトルールをフィルター |
| `search_rewrite_rules` | `(array $rules)` | 検索のリライトルールをフィルター |
| `author_rewrite_rules` | `(array $rules)` | 著者のリライトルールをフィルター |
| `page_rewrite_rules` | `(array $rules)` | 固定ページのリライトルールをフィルター |
| `{$permastructname}_rewrite_rules` | `(array $rules)` | カスタムパーマリンク構造のルールをフィルター |
| `mod_rewrite_rules` | `(string $rules)` | .htaccess に書き込むルール文字列をフィルター |
| `iis7_url_rewrite_rules` | `(string $rules)` | IIS web.config のルールをフィルター |
| `pre_get_shortlink` | `(false\|string $shortlink, int $id, string $context, bool $allow_slugs)` | ショートリンク取得前のフィルター |
| `get_shortlink` | `(string $shortlink, int $id, string $context, bool $allow_slugs)` | ショートリンクをフィルター |
| `redirect_canonical` | `(string $redirect_url, string $requested_url)` | 正規 URL リダイレクト先をフィルター。`false` でリダイレクト無効化 |
| `rewrite_rules` | `(string $rules)` | `mod_rewrite_rules` の非推奨エイリアス |
