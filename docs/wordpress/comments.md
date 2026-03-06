# WordPress コメント API 仕様

## 1. 概要

WordPress のコメントシステムは、投稿に対するユーザーのフィードバックを管理する仕組みです。コメント、ピンバック、トラックバックの 3 種類をサポートし、スレッド型（ネスト）表示、スパム防止、モデレーションワークフローを提供します。

コメントデータは `wp_comments` テーブルに格納され、メタデータは `wp_commentmeta` テーブルで管理されます。コメントの取得には `WP_Comment_Query` クラスが使用されます。

| グローバル変数 | 型 | 説明 |
|---|---|---|
| `$wp_comment_query` | N/A | `WP_Comment_Query` の一時インスタンス（内部使用） |

### 主要ファイル

| ファイル | 説明 |
|---|---|
| `wp-includes/class-wp-comment.php` | `WP_Comment` クラス |
| `wp-includes/class-wp-comment-query.php` | `WP_Comment_Query` クラス |
| `wp-includes/comment.php` | コメント関連関数群 |
| `wp-includes/comment-template.php` | コメントテンプレート関数群 |

## 2. データ構造

### WP_Comment クラス

```php
final class WP_Comment {
    public int    $comment_ID;              // コメント ID
    public int    $comment_post_ID;         // 対象投稿 ID
    public string $comment_author;          // コメント者名
    public string $comment_author_email;    // メールアドレス
    public string $comment_author_url;      // URL
    public string $comment_author_IP;       // IP アドレス
    public string $comment_date;            // コメント日時（ローカル）
    public string $comment_date_gmt;        // コメント日時（GMT）
    public string $comment_content;         // コメント本文
    public int    $comment_karma;           // カルマスコア（デフォルト: 0。未使用）
    public string $comment_approved;        // 承認ステータス（'1', '0', 'spam', 'trash', 'post-trashed'）
    public string $comment_agent;           // ユーザーエージェント
    public string $comment_type;            // コメントタイプ（'comment', 'pingback', 'trackback', カスタム）
    public int    $comment_parent;          // 親コメント ID（0 = トップレベル）
    public int    $user_id;                 // WordPress ユーザー ID（0 = 未ログイン）
    public string $filter;                  // サニタイズコンテキスト
}
```

`WP_Comment::get_instance($comment_id)` でオブジェクトキャッシュから取得します。`get_children()` メソッドで子コメント（返信）を遅延取得できます。

### コメント承認ステータス

| 値 | 定数 | 説明 |
|---|---|---|
| `'1'` | — | 承認済み（公開表示） |
| `'0'` | — | 未承認（モデレーション待ち） |
| `'spam'` | — | スパム |
| `'trash'` | — | ゴミ箱 |
| `'post-trashed'` | — | 親投稿がゴミ箱に移動された |

### コメントタイプ

| タイプ | 説明 |
|---|---|
| `'comment'` | 通常のコメント（デフォルト。空文字列も `'comment'` として扱われる） |
| `'pingback'` | ピンバック |
| `'trackback'` | トラックバック |
| `'pings'` | クエリ用エイリアス（pingback + trackback） |
| カスタム文字列 | プラグインによるカスタムコメントタイプ |

### テーブル構造

#### wp_comments

| カラム | 型 | 説明 |
|---|---|---|
| `comment_ID` | `bigint(20) unsigned` | コメント ID（PRIMARY KEY, AUTO_INCREMENT） |
| `comment_post_ID` | `bigint(20) unsigned` | 対象投稿 ID |
| `comment_author` | `tinytext` | コメント者名 |
| `comment_author_email` | `varchar(100)` | メールアドレス |
| `comment_author_url` | `varchar(200)` | URL |
| `comment_author_IP` | `varchar(100)` | IP アドレス |
| `comment_date` | `datetime` | コメント日時（ローカル） |
| `comment_date_gmt` | `datetime` | コメント日時（GMT） |
| `comment_content` | `text` | コメント本文 |
| `comment_karma` | `int(11)` | カルマスコア（デフォルト: 0） |
| `comment_approved` | `varchar(20)` | 承認ステータス |
| `comment_agent` | `varchar(255)` | ユーザーエージェント |
| `comment_type` | `varchar(20)` | コメントタイプ（デフォルト: `'comment'`） |
| `comment_parent` | `bigint(20) unsigned` | 親コメント ID（0 = トップレベル） |
| `user_id` | `bigint(20) unsigned` | WordPress ユーザー ID（0 = 未ログイン） |

**インデックス:**

| インデックス名 | カラム | 説明 |
|---|---|---|
| `PRIMARY` | `comment_ID` | 主キー |
| `comment_post_ID` | `comment_post_ID` | 投稿別コメント取得 |
| `comment_approved_date_gmt` | `comment_approved, comment_date_gmt` | 承認済み最新コメント取得 |
| `comment_date_gmt` | `comment_date_gmt` | 日時順取得 |
| `comment_parent` | `comment_parent` | 子コメント取得 |
| `comment_author_email` | `comment_author_email(10)` | メールアドレス検索 |

#### wp_commentmeta

| カラム | 型 | 説明 |
|---|---|---|
| `meta_id` | `bigint(20) unsigned` | メタ ID（PRIMARY KEY, AUTO_INCREMENT） |
| `comment_id` | `bigint(20) unsigned` | コメント ID |
| `meta_key` | `varchar(255)` | メタキー |
| `meta_value` | `longtext` | メタ値 |

**インデックス:**

| インデックス名 | カラム |
|---|---|
| `PRIMARY` | `meta_id` |
| `comment_id` | `comment_id` |
| `meta_key` | `meta_key(191)` |

## 3. API リファレンス

### コメント CRUD API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_insert_comment()` | `(array $commentdata): int\|false` | コメントを DB に直接挿入（フィルタなし） |
| `wp_new_comment()` | `(array $commentdata, bool $wp_error = false): int\|false\|WP_Error` | コメントを追加（バリデーション・スパムチェック・通知含む） |
| `wp_update_comment()` | `(array $commentarr, bool $wp_error = false): int\|false\|WP_Error` | コメントを更新 |
| `wp_delete_comment()` | `(int\|WP_Comment $comment_id, bool $force_delete = false): bool` | コメントを削除（デフォルトはゴミ箱へ） |
| `wp_trash_comment()` | `(int\|WP_Comment $comment_id): bool` | コメントをゴミ箱に移動 |
| `wp_untrash_comment()` | `(int\|WP_Comment $comment_id): bool` | コメントをゴミ箱から復元 |
| `wp_spam_comment()` | `(int\|WP_Comment $comment_id): bool` | コメントをスパムとしてマーク |
| `wp_unspam_comment()` | `(int\|WP_Comment $comment_id): bool` | スパムマークを解除 |
| `get_comment()` | `(int\|WP_Comment $comment = null, string $output = OBJECT): WP_Comment\|array\|null` | コメントを取得 |
| `get_comments()` | `(string\|array $args = ''): WP_Comment[]\|int[]\|int` | コメント一覧を取得 |

#### `wp_new_comment()` と `wp_insert_comment()` の違い

| 観点 | `wp_new_comment()` | `wp_insert_comment()` |
|---|---|---|
| 用途 | フロントエンドからのコメント投稿 | DB への直接挿入（インポート等） |
| バリデーション | あり（重複チェック、フラッドチェック） | なし |
| スパムチェック | あり（`pre_comment_approved` フィルタ） | なし |
| サニタイズ | あり（`wp_filter_comment()`) | なし |
| 通知 | あり（`wp_notify_postauthor()`, `wp_notify_moderator()`） | なし |
| フック | 全フック発火 | `wp_insert_comment` のみ |
| 戻り値 | コメント ID or WP_Error | コメント ID or false |

#### `wp_new_comment()` の `$commentdata` パラメータ

| キー | 型 | 説明 |
|---|---|---|
| `comment_post_ID` | `int` | 対象投稿 ID（必須） |
| `comment_author` | `string` | コメント者名 |
| `comment_author_email` | `string` | メールアドレス |
| `comment_author_url` | `string` | URL |
| `comment_content` | `string` | コメント本文（必須） |
| `comment_type` | `string` | コメントタイプ（デフォルト: `'comment'`） |
| `comment_parent` | `int` | 親コメント ID（デフォルト: `0`） |
| `user_id` | `int` | ユーザー ID（デフォルト: `0`） |
| `comment_author_IP` | `string` | IP アドレス（自動設定） |
| `comment_agent` | `string` | ユーザーエージェント（自動設定） |
| `comment_date` | `string` | コメント日時（自動設定） |
| `comment_date_gmt` | `string` | コメント日時 GMT（自動設定） |
| `comment_approved` | `string\|int` | 承認ステータス（自動判定） |

### コメントステータス API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_set_comment_status()` | `(int\|WP_Comment $comment_id, string $comment_status, bool $wp_error = false): bool\|WP_Error` | コメントのステータスを変更 |
| `wp_get_comment_status()` | `(int\|WP_Comment $comment_id): string\|false` | コメントのステータスを取得（`'approved'`, `'unapproved'`, `'spam'`, `'trash'`） |
| `wp_transition_comment_status()` | `(string $new_status, string $old_status, WP_Comment $comment): void` | ステータス遷移フックを発火 |
| `wp_count_comments()` | `(int $post_id = 0): object` | コメント数を集計（`approved`, `moderated`, `spam`, `trash`, `post-trashed`, `total_comments`, `all`） |
| `wp_update_comment_count()` | `(int $post_id, bool $do_deferred = false): bool\|void` | 投稿のコメント数を更新 |
| `wp_update_comment_count_now()` | `(int $post_id): bool` | 投稿のコメント数を即座に更新 |
| `wp_defer_comment_counting()` | `(bool $defer = null): bool` | コメント数更新の遅延制御 |

### コメントメタ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_comment_meta()` | `(int $comment_id, string $key = '', bool $single = false): mixed` | コメントメタデータを取得 |
| `add_comment_meta()` | `(int $comment_id, string $meta_key, mixed $meta_value, bool $unique = false): int\|false` | コメントメタデータを追加 |
| `update_comment_meta()` | `(int $comment_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int\|bool` | コメントメタデータを更新 |
| `delete_comment_meta()` | `(int $comment_id, string $meta_key, mixed $meta_value = ''): bool` | コメントメタデータを削除 |

### コメントクエリ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_approved_comments()` | `(int $post_id, array $args = []): WP_Comment[]\|int[]` | 承認済みコメントを取得 |
| `get_comment_count()` | `(int $post_id = 0): array` | コメント数の連想配列を返す |

### コメント設定 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `comments_open()` | `(int\|WP_Post $post = null): bool` | コメントが受付中か |
| `pings_open()` | `(int\|WP_Post $post = null): bool` | ピンバックが受付中か |
| `get_comment_pages_count()` | `(WP_Comment[] $comments = null, int $per_page = null, bool $threaded = null): int` | コメントのページ数を取得 |

### WP_Comment_Query

`WP_Comment_Query` はコメントを検索するためのクラスで、`get_comments()` の内部実装です。

#### 主要パラメータ

**基本フィルタ:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `author_email` | `string` | `''` | メールアドレスで絞り込み |
| `author_url` | `string` | `''` | URL で絞り込み |
| `author__in` | `int[]` | `[]` | 指定ユーザー ID のコメント |
| `author__not_in` | `int[]` | `[]` | 除外するユーザー ID |
| `comment__in` | `int[]` | `[]` | 含めるコメント ID |
| `comment__not_in` | `int[]` | `[]` | 除外するコメント ID |
| `karma` | `int` | `''` | カルマ値で絞り込み |
| `parent` | `int\|string` | `''` | 親コメント ID |
| `parent__in` | `int[]` | `[]` | 含める親コメント ID |
| `parent__not_in` | `int[]` | `[]` | 除外する親コメント ID |
| `search` | `string` | `''` | コメント本文の部分一致検索 |

**投稿関連:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `post_id` | `int` | `0` | 投稿 ID |
| `post__in` | `int[]` | `[]` | 含める投稿 ID |
| `post__not_in` | `int[]` | `[]` | 除外する投稿 ID |
| `post_author` | `int` | `''` | 投稿の著者 ID |
| `post_author__in` | `int[]` | `[]` | 含める投稿著者 ID |
| `post_author__not_in` | `int[]` | `[]` | 除外する投稿著者 ID |
| `post_type` | `string\|string[]` | `''` | 投稿タイプ |
| `post_status` | `string\|string[]` | `''` | 投稿ステータス |
| `post_name` | `string` | `''` | 投稿スラッグ |
| `post_parent` | `int` | `''` | 投稿の親 ID |

**ステータス・タイプ:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `status` | `string\|string[]` | `''` | コメントステータス（`'approve'`, `'hold'`, `'spam'`, `'trash'`, `'all'`, `'any'`） |
| `type` | `string\|string[]` | `''` | コメントタイプ（`'comment'`, `'pingback'`, `'trackback'`, `'pings'`） |
| `type__in` | `string[]` | `[]` | 含めるコメントタイプ |
| `type__not_in` | `string[]` | `[]` | 除外するコメントタイプ |

**並び替え・ページネーション:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `orderby` | `string\|array` | `'comment_date_gmt'` | ソートフィールド |
| `order` | `string` | `'DESC'` | ソート方向 |
| `number` | `int` | `''` | 取得件数 |
| `offset` | `int` | `0` | スキップ件数 |
| `paged` | `int` | `1` | ページ番号 |

**日付:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `date_query` | `array` | `null` | 日付クエリ配列（`WP_Date_Query` 構文） |

**メタ:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `meta_key` | `string` | `''` | メタキー |
| `meta_value` | `string` | `''` | メタ値 |
| `meta_compare` | `string` | `'='` | 比較演算子 |
| `meta_type` | `string` | `''` | キャスト型 |
| `meta_query` | `array` | `[]` | メタクエリ配列 |

**階層・キャッシュ:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `hierarchical` | `bool\|string` | `false` | 階層データを含むか（`true`, `'flat'`, `'threaded'`） |
| `no_found_rows` | `bool` | `true` | `SQL_CALC_FOUND_ROWS` を省略（デフォルトで省略） |
| `cache_domain` | `string` | `'core'` | キャッシュドメイン |
| `update_comment_meta_cache` | `bool` | `true` | メタキャッシュを更新するか |
| `update_comment_post_cache` | `bool` | `false` | 投稿キャッシュを更新するか |

**フィールド:**

| パラメータ | 型 | デフォルト | 説明 |
|---|---|---|---|
| `fields` | `string` | `''` | `''`（全フィールド）、`'ids'`（ID のみ）、`'count'`（件数のみ） |
| `count` | `bool` | `false` | `true` でコメント件数のみ返す |

#### `orderby` で指定できる値

| 値 | 説明 |
|---|---|
| `'comment_agent'` | ユーザーエージェント |
| `'comment_approved'` | 承認ステータス |
| `'comment_author'` | コメント者名 |
| `'comment_author_email'` | メールアドレス |
| `'comment_author_IP'` | IP アドレス |
| `'comment_author_url'` | URL |
| `'comment_content'` | コメント本文 |
| `'comment_date'` | コメント日時 |
| `'comment_date_gmt'` | コメント日時（GMT、デフォルト） |
| `'comment_ID'` | コメント ID |
| `'comment_karma'` | カルマスコア |
| `'comment_parent'` | 親コメント ID |
| `'comment_post_ID'` | 投稿 ID |
| `'comment_type'` | コメントタイプ |
| `'user_id'` | ユーザー ID |
| `'comment__in'` | `comment__in` の順序を維持 |
| `'meta_value'` | メタ値（`meta_key` 必須） |
| `'meta_value_num'` | メタ値（数値） |
| 名前付き `meta_query` 句 | メタクエリ句名 |

### コメントテンプレート関数

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_list_comments()` | `(string\|array $args = [], WP_Comment[] $comments = null): void\|string` | コメント一覧を HTML リスト表示 |
| `comment_form()` | `(array $args = [], int\|WP_Post $post = null): void` | コメントフォームを出力 |
| `get_comment_author()` | `(int\|WP_Comment $comment_id = 0): string` | コメント者名を取得 |
| `get_comment_date()` | `(string $format = '', int\|WP_Comment $comment_id = 0): string` | コメント日時を取得 |
| `get_comment_text()` | `(int\|WP_Comment $comment_id = 0, array $args = []): string` | コメント本文を取得 |
| `get_comment_link()` | `(WP_Comment\|int\|null $comment = null, array $args = []): string` | コメントへのパーマリンクを取得 |
| `get_comment_reply_link()` | `(array $args = [], WP_Comment\|int\|null $comment = null, WP_Post\|int\|null $post = null): string\|false\|null` | 返信リンクを取得 |
| `get_cancel_comment_reply_link()` | `(string $text = ''): string` | 返信キャンセルリンクを取得 |
| `get_comment_class()` | `(string\|string[] $css_class = '', int\|WP_Comment\|null $comment = null, int\|WP_Post\|null $post = null): string[]` | コメントの CSS クラス配列を取得 |
| `comments_template()` | `(string $file = '/comments.php', bool $separate_comments = false): void` | コメントテンプレートをインクルード |

#### `wp_list_comments()` の主要引数

| 引数 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `walker` | `Walker_Comment` | `null` | カスタム Walker クラス |
| `max_depth` | `int` | — | スレッドの最大深度（設定値 `thread_comments_depth` から） |
| `style` | `string` | `'ol'` | リストスタイル（`'ol'`, `'ul'`, `'div'`） |
| `callback` | `callable` | `null` | カスタムコールバック関数 |
| `end-callback` | `callable` | `null` | 終了タグコールバック |
| `type` | `string` | `'all'` | 表示タイプ（`'all'`, `'comment'`, `'pingback'`, `'trackback'`, `'pings'`） |
| `page` | `int` | — | 表示するページ番号 |
| `per_page` | `int` | — | 1 ページあたりのコメント数 |
| `avatar_size` | `int` | `32` | アバター画像のサイズ（px） |
| `reverse_top_level` | `bool` | `null` | トップレベルコメントの並び順を反転 |
| `reverse_children` | `bool` | `false` | 子コメントの並び順を反転 |
| `format` | `string` | `'html5'` | 出力フォーマット |
| `short_ping` | `bool` | `false` | ピンバック/トラックバックの短縮表示 |
| `echo` | `bool` | `true` | `false` で文字列として返す |

### コメント通知 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_notify_postauthor()` | `(int\|WP_Comment $comment_id, string $deprecated = null): bool` | 投稿者にコメント通知メールを送信 |
| `wp_notify_moderator()` | `(int $comment_id): true` | モデレーターにコメント承認通知メールを送信 |
| `wp_new_comment_notify_postauthor()` | `(int $comment_id): bool` | 投稿者通知のラッパー |
| `wp_new_comment_notify_moderator()` | `(int $comment_id): bool` | モデレーター通知のラッパー |

### フラッド・スパム制御 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_check_comment_flood()` | `(bool $is_flood, string $ip, string $email, string $date, bool $avoid_die = false): bool` | コメントフラッドをチェック |
| `check_comment()` | `(string $author, string $email, string $url, string $comment, string $user_ip, string $user_agent, string $comment_type): bool` | コメントのモデレーション判定 |
| `wp_check_comment_disallowed_list()` | `(string $author, string $email, string $url, string $comment, string $user_ip, string $user_agent): bool` | 禁止リストチェック（旧 `wp_blacklist_check()`） |
| `wp_allow_comment()` | `(array $commentdata, bool $wp_error = false): int\|string\|WP_Error` | コメント投稿の許可判定。承認ステータスを返す |
| `wp_filter_comment()` | `(array $commentdata): array` | コメントデータのフィルタリング |

### コメントキャッシュ API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `clean_comment_cache()` | `(int\|int[] $ids): void` | コメントキャッシュをクリア |
| `update_comment_cache()` | `(WP_Comment[] $comments, bool $update_meta_cache = true): void` | コメントキャッシュを更新 |

## 4. 実行フロー

### `wp_new_comment()` の実行フロー

```
wp_new_comment($commentdata)
│
├── データ補完
│   ├── comment_author_IP = $_SERVER['REMOTE_ADDR']
│   ├── comment_agent = $_SERVER['HTTP_USER_AGENT']
│   ├── comment_date = current_time('mysql')
│   └── comment_date_gmt = current_time('mysql', 1)
│
├── apply_filters('preprocess_comment', $commentdata)
│
├── サニタイズ
│   └── wp_filter_comment($commentdata)
│       ├── apply_filters('pre_comment_author_name', $author)
│       ├── apply_filters('pre_comment_author_email', $email)
│       ├── apply_filters('pre_comment_author_url', $url)
│       ├── apply_filters('pre_comment_content', $content)
│       ├── apply_filters('pre_comment_user_ip', $ip)
│       └── apply_filters('pre_comment_user_agent', $agent)
│
├── フラッドチェック
│   └── wp_check_comment_flood()
│       ├── 同一 IP/メールからの直近コメントを検索
│       ├── apply_filters('comment_flood_filter', false, $time_lastcomment, $time_newcomment)
│       └── フラッド判定: die() or WP_Error
│
├── 承認ステータスの判定
│   └── wp_allow_comment($commentdata)
│       ├── ログインユーザーの権限チェック
│       ├── 以前承認済みコメントの有無
│       ├── check_comment()（モデレーションキーワードチェック）
│       ├── wp_check_comment_disallowed_list()（禁止リストチェック）
│       └── apply_filters('pre_comment_approved', $approved, $commentdata)
│           └── 戻り値: 1（承認）, 0（保留）, 'spam'
│
├── $commentdata['comment_approved'] = $approved
│
├── wp_insert_comment($commentdata)
│   ├── $wpdb->insert($wpdb->comments, $data)
│   ├── $comment_id = $wpdb->insert_id
│   ├── clean_comment_cache($comment_id)
│   ├── wp_update_comment_count($comment_post_ID)
│   └── do_action('wp_insert_comment', $comment_id, $comment)
│
├── do_action('comment_post', $comment_id, $approved, $commentdata)
│
├── 通知メール送信
│   ├── $approved == 1:
│   │   └── wp_notify_postauthor($comment_id)
│   └── $approved == 0:
│       └── wp_notify_moderator($comment_id)
│
└── return $comment_id
```

### `wp_delete_comment()` の実行フロー

```
wp_delete_comment($comment_id, $force_delete = false)
│
├── $comment = get_comment($comment_id)
│
├── $force_delete == false かつ EMPTY_TRASH_DAYS > 0
│   └── wp_trash_comment($comment_id)
│       ├── do_action('trash_comment', $comment_id, $comment)
│       ├── add_comment_meta($comment_id, '_wp_trash_meta_status', $comment->comment_approved)
│       ├── add_comment_meta($comment_id, '_wp_trash_meta_time', time())
│       ├── wp_set_comment_status($comment_id, 'trash')
│       └── do_action('trashed_comment', $comment_id, $comment)
│
├── $force_delete == true
│   ├── do_action('delete_comment', $comment_id, $comment)
│   │
│   ├── 子コメントの処理
│   │   └── 子コメントの parent を現コメントの parent に付け替え
│   │       └── $wpdb->update($wpdb->comments, ['comment_parent' => $comment->comment_parent],
│   │           ['comment_parent' => $comment_id])
│   │
│   ├── コメントメタの削除
│   │   └── $wpdb->delete($wpdb->commentmeta, ['comment_id' => $comment_id])
│   │
│   ├── コメントの削除
│   │   └── $wpdb->delete($wpdb->comments, ['comment_ID' => $comment_id])
│   │
│   ├── 投稿のコメント数を更新
│   │   └── wp_update_comment_count($post_id)
│   │
│   ├── キャッシュクリア
│   │   └── clean_comment_cache($comment_id)
│   │
│   └── do_action('deleted_comment', $comment_id, $comment)
│
└── return true
```

### WP_Comment_Query の実行フロー

```
new WP_Comment_Query(['post_id' => 42, 'status' => 'approve'])
│
├── $this->query($args)
│   │
│   ├── $this->parse_query($args)
│   │   └── デフォルト値のマージ
│   │
│   └── $this->get_comments()
│       │
│       ├── do_action('pre_get_comments', $this)
│       │
│       ├── キャッシュチェック
│       │   └── wp_cache_get($cache_key, 'comment-queries')
│       │
│       ├── SQL 構築
│       │   ├── SELECT: $fields パラメータに基づく
│       │   │
│       │   ├── FROM: $wpdb->comments AS comment
│       │   │
│       │   ├── JOIN:
│       │   │   ├── meta_query → $wpdb->commentmeta JOIN
│       │   │   └── post_* パラメータ → $wpdb->posts JOIN
│       │   │
│       │   ├── WHERE:
│       │   │   ├── comment_post_ID = 42
│       │   │   ├── comment_approved = '1'
│       │   │   ├── author_email, author_url
│       │   │   ├── comment__in, comment__not_in
│       │   │   ├── parent, parent__in, parent__not_in
│       │   │   ├── type, type__in, type__not_in
│       │   │   ├── search → comment_content LIKE '%...%'
│       │   │   ├── date_query → WP_Date_Query::get_sql()
│       │   │   └── meta_query → WP_Meta_Query::get_sql()
│       │   │
│       │   ├── ORDER BY: comment_date_gmt DESC
│       │   └── LIMIT / OFFSET
│       │
│       ├── apply_filters('comments_clauses', $clauses, $this)
│       │
│       ├── apply_filters('the_comments', null, $this)  // ショートサーキット
│       │
│       ├── $wpdb->get_results($sql)
│       │
│       ├── WP_Comment オブジェクトへの変換
│       │   └── array_map('get_comment', $results)
│       │
│       ├── キャッシュのプライミング
│       │   ├── update_comment_meta_cache
│       │   └── update_comment_post_cache
│       │
│       ├── 階層データの構築（hierarchical パラメータ）
│       │   ├── 'threaded': 子コメントを親の children に格納
│       │   └── 'flat': 階層順でフラット配列に並び替え
│       │
│       ├── キャッシュに格納
│       │
│       └── return $this->comments
```

### コメントテンプレート表示フロー

```
comments_template()
│
├── コメントテンプレートファイルの検索
│   ├── テーマ内の comments.php
│   └── wp-includes/theme-compat/comments.php（フォールバック）
│
├── $comments = get_approved_comments($post->ID) or WP_Comment_Query 実行
│
├── wp_list_comments($args, $comments)
│   ├── Walker_Comment を使用してツリーを走査
│   │
│   ├── 各コメントについて:
│   │   ├── comment_class() で CSS クラスを生成
│   │   ├── get_comment_author_link() で著者リンク
│   │   ├── get_avatar() でアバター画像
│   │   ├── get_comment_text() でコメント本文
│   │   │   └── apply_filters('comment_text', $text, $comment, $args)
│   │   ├── get_comment_reply_link() で返信リンク
│   │   └── 子コメント（$comment->get_children()）を再帰的に表示
│   │
│   └── HTML 出力（ol/ul/div）
│
└── comment_form()
    ├── apply_filters('comment_form_defaults', $defaults)
    ├── do_action('comment_form_before')
    ├── フォーム HTML 出力
    │   ├── do_action('comment_form_top')
    │   ├── フィールド出力（名前、メール、URL、本文）
    │   │   └── apply_filters('comment_form_field_{$name}', $field)
    │   ├── do_action('comment_form')
    │   └── 送信ボタン
    └── do_action('comment_form_after')
```

## 5. フック一覧

### コメント保存

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `preprocess_comment` | Filter | `(array $commentdata): array` | コメント処理の最初のフィルタ |
| `pre_comment_author_name` | Filter | `(string $author): string` | コメント者名のサニタイズ |
| `pre_comment_author_email` | Filter | `(string $email): string` | メールアドレスのサニタイズ |
| `pre_comment_author_url` | Filter | `(string $url): string` | URL のサニタイズ |
| `pre_comment_content` | Filter | `(string $content): string` | コメント本文のサニタイズ |
| `pre_comment_user_ip` | Filter | `(string $ip): string` | IP アドレスのサニタイズ |
| `pre_comment_user_agent` | Filter | `(string $agent): string` | ユーザーエージェントのサニタイズ |
| `pre_comment_approved` | Filter | `(int\|string $approved, array $commentdata): int\|string` | 承認ステータスの判定 |
| `comment_flood_filter` | Filter | `(bool $is_flood, string $time_lastcomment, string $time_newcomment): bool` | フラッド判定 |
| `wp_insert_comment` | Action | `(int $comment_id, WP_Comment $comment)` | コメント DB 挿入後 |
| `comment_post` | Action | `(int $comment_id, int\|string $approved, array $commentdata)` | `wp_new_comment()` でのコメント投稿後 |
| `wp_update_comment_data` | Filter | `(array $data, array $comment, array $commentarr): array` | コメント更新前のデータをフィルタ |
| `edit_comment` | Action | `(int $comment_id, array $data)` | コメント更新後 |

### コメント削除

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `trash_comment` | Action | `(int $comment_id, WP_Comment $comment)` | ゴミ箱移動前 |
| `trashed_comment` | Action | `(int $comment_id, WP_Comment $comment)` | ゴミ箱移動後 |
| `untrash_comment` | Action | `(int $comment_id, WP_Comment $comment)` | ゴミ箱復元前 |
| `untrashed_comment` | Action | `(int $comment_id, WP_Comment $comment)` | ゴミ箱復元後 |
| `spam_comment` | Action | `(int $comment_id, WP_Comment $comment)` | スパムマーク前 |
| `spammed_comment` | Action | `(int $comment_id, WP_Comment $comment)` | スパムマーク後 |
| `unspam_comment` | Action | `(int $comment_id, WP_Comment $comment)` | スパム解除前 |
| `unspammed_comment` | Action | `(int $comment_id, WP_Comment $comment)` | スパム解除後 |
| `delete_comment` | Action | `(int $comment_id, WP_Comment $comment)` | 完全削除前 |
| `deleted_comment` | Action | `(int $comment_id, WP_Comment $comment)` | 完全削除後 |

### コメントステータス遷移

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `transition_comment_status` | Action | `(string $new_status, string $old_status, WP_Comment $comment)` | ステータス変更時 |
| `comment_{$old_status}_to_{$new_status}` | Action | `(WP_Comment $comment)` | 特定のステータス遷移 |
| `comment_{$new_status}_{$comment_type}` | Action | `(int $comment_id, WP_Comment $comment)` | ステータス・タイプの組み合わせ |

### コメント取得

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `get_comment` | Filter | `(WP_Comment $comment): WP_Comment` | コメント取得時 |
| `pre_get_comments` | Action | `(WP_Comment_Query &$query)` | コメントクエリ実行前 |
| `comments_clauses` | Filter | `(array $clauses, WP_Comment_Query $query): array` | SQL 句をフィルタ |
| `the_comments` | Filter | `(WP_Comment[]\|null $comments, WP_Comment_Query $query): WP_Comment[]\|null` | クエリ結果をフィルタ（ショートサーキット可能） |

### コメント表示

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `comment_text` | Filter | `(string $text, WP_Comment\|null $comment, array $args): string` | コメント本文の表示 |
| `get_comment_text` | Filter | `(string $text, WP_Comment $comment, array $args): string` | コメント本文の取得 |
| `get_comment_author` | Filter | `(string $author, int $comment_id, WP_Comment $comment): string` | コメント者名 |
| `get_comment_author_link` | Filter | `(string $link): string` | コメント者リンク |
| `comment_author` | Filter | `(string $author, int $comment_id): string` | コメント者名表示 |
| `get_comment_date` | Filter | `(string $date, string $format, WP_Comment $comment): string` | コメント日時 |
| `get_comment_link` | Filter | `(string $link, WP_Comment $comment, array $args, int $page): string` | コメントパーマリンク |
| `comment_reply_link` | Filter | `(string $link, array $args, WP_Comment $comment, WP_Post $post): string` | 返信リンク |
| `comment_class` | Filter | `(string[] $classes, string[] $css_class, int $comment_id, WP_Comment $comment, int $post_id): string[]` | コメント CSS クラス |

### コメントフォーム

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `comment_form_defaults` | Filter | `(array $defaults): array` | フォームデフォルト設定 |
| `comment_form_default_fields` | Filter | `(array $fields): array` | デフォルトフィールド |
| `comment_form_field_{$name}` | Filter | `(string $field): string` | 各フィールドの HTML |
| `comment_form_before` | Action | — | フォーム出力前 |
| `comment_form_top` | Action | — | フォーム内の先頭 |
| `comment_form` | Action | `(int $post_id)` | フォームフィールド後・送信ボタン前 |
| `comment_form_after` | Action | — | フォーム出力後 |
| `comment_form_comments_closed` | Action | — | コメント締切時 |

### コメント通知

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `notify_post_author` | Filter | `(bool $notify, int $comment_id): bool` | 投稿者通知を送信するか |
| `notify_moderator` | Filter | `(bool $notify, int $comment_id): bool` | モデレーター通知を送信するか |
| `comment_notification_text` | Filter | `(string $text, int $comment_id): string` | 通知メール本文 |
| `comment_notification_subject` | Filter | `(string $subject, int $comment_id): string` | 通知メール件名 |
| `comment_notification_headers` | Filter | `(string $headers, int $comment_id): string` | 通知メールヘッダー |
| `comment_moderation_text` | Filter | `(string $text, int $comment_id): string` | モデレーション通知メール本文 |
| `comment_moderation_subject` | Filter | `(string $subject, int $comment_id): string` | モデレーション通知メール件名 |
| `comment_moderation_headers` | Filter | `(string $headers, int $comment_id): string` | モデレーション通知メールヘッダー |

### コメント数

| フック名 | 種別 | 引数 | 説明 |
|---|---|---|---|
| `wp_update_comment_count_now` | Filter | `(int $new_count, int $old_count, int $post_id): int` | コメント数更新時 |
| `wp_count_comments` | Filter | `(object $count, int $post_id): object` | コメント数集計結果 |

## 6. コメント設定（wp_options）

WordPress のコメントシステムは以下のオプションで制御されます:

| オプション名 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `default_comment_status` | `string` | `'open'` | 新規投稿のデフォルトコメント状態 |
| `default_ping_status` | `string` | `'open'` | 新規投稿のデフォルトピンバック状態 |
| `require_name_email` | `bool` | `1` | 名前・メールを必須にするか |
| `comment_registration` | `bool` | `0` | ログインユーザーのみコメント可能か |
| `close_comments_for_old_posts` | `bool` | `0` | 古い投稿のコメントを自動締切 |
| `close_comments_days_old` | `int` | `14` | コメント締切までの日数 |
| `thread_comments` | `bool` | `1` | スレッド型コメントを有効化 |
| `thread_comments_depth` | `int` | `5` | スレッドの最大深度 |
| `page_comments` | `bool` | `0` | コメントをページ分割 |
| `comments_per_page` | `int` | `50` | 1 ページあたりのコメント数 |
| `default_comments_page` | `string` | `'newest'` | デフォルトで表示するページ（`'newest'` / `'oldest'`） |
| `comment_order` | `string` | `'asc'` | コメントの並び順 |
| `comment_moderation` | `bool` | `0` | 手動承認を必須にするか |
| `comment_previously_approved` | `bool` | `1` | 以前承認済みの場合は自動承認 |
| `comment_max_links` | `int` | `2` | この数以上のリンクを含むコメントを保留 |
| `moderation_keys` | `string` | `''` | モデレーションキーワード（改行区切り） |
| `disallowed_keys` | `string` | `''` | 禁止キーワード（改行区切り） |
| `show_avatars` | `bool` | `1` | アバターを表示するか |
| `avatar_default` | `string` | `'mystery'` | デフォルトアバター |
| `avatar_rating` | `string` | `'G'` | アバターのレーティング制限 |
