# WordPress データベース API 仕様

## 1. 概要

WordPress のデータベースアクセスは `wpdb` クラス（`wp-includes/class-wpdb.php`）を通じて行われます。グローバル変数 `$wpdb` にインスタンスが格納され、すべてのコア機能はこのオブジェクトを介してデータベースとやり取りします。

`wpdb` は MySQL / MariaDB に特化した軽量なデータベース抽象化レイヤーで、以下の機能を提供します:

- プリペアドステートメントによる SQL インジェクション防止
- CRUD 操作のヘルパーメソッド
- テーブル名のプレフィックス管理
- クエリ結果のキャッシュ
- エラーハンドリングとデバッグ支援

### データベース接続

`wpdb` は `wp-config.php` で定義された以下の定数を使用して接続します:

| 定数 | 説明 |
|---|---|
| `DB_NAME` | データベース名 |
| `DB_USER` | データベースユーザー名 |
| `DB_PASSWORD` | データベースパスワード |
| `DB_HOST` | データベースホスト |
| `DB_CHARSET` | 文字セット（デフォルト: `utf8mb4`） |
| `DB_COLLATE` | 照合順序（デフォルト: 空文字列） |

## 2. データ構造

### wpdb クラスのプロパティ

#### テーブル名プロパティ

`wpdb` はすべてのコアテーブル名をプロパティとして保持します。テーブルプレフィックス（デフォルト: `wp_`）が自動的に付与されます。

| プロパティ | テーブル名 | 説明 |
|---|---|---|
| `$posts` | `wp_posts` | 投稿・ページ・カスタム投稿タイプ |
| `$postmeta` | `wp_postmeta` | 投稿メタデータ |
| `$comments` | `wp_comments` | コメント |
| `$commentmeta` | `wp_commentmeta` | コメントメタデータ |
| `$terms` | `wp_terms` | タクソノミーの用語 |
| `$termmeta` | `wp_termmeta` | 用語メタデータ |
| `$term_taxonomy` | `wp_term_taxonomy` | 用語とタクソノミーの関連 |
| `$term_relationships` | `wp_term_relationships` | オブジェクトと用語の関連 |
| `$users` | `wp_users` | ユーザー |
| `$usermeta` | `wp_usermeta` | ユーザーメタデータ |
| `$options` | `wp_options` | サイトオプション |
| `$links` | `wp_links` | リンク（非推奨） |

#### 状態管理プロパティ

| プロパティ | 型 | 説明 |
|---|---|---|
| `$prefix` | `string` | テーブルプレフィックス（デフォルト: `wp_`） |
| `$base_prefix` | `string` | マルチサイトにおけるベースプレフィックス |
| `$last_query` | `string` | 最後に実行されたクエリ |
| `$last_result` | `array` | 最後のクエリ結果 |
| `$last_error` | `string` | 最後のエラーメッセージ |
| `$insert_id` | `int` | 最後の `INSERT` で生成された AUTO_INCREMENT ID |
| `$num_rows` | `int` | 最後のクエリで返された行数 |
| `$rows_affected` | `int` | 最後のクエリで影響を受けた行数 |
| `$num_queries` | `int` | 実行されたクエリの総数 |
| `$queries` | `array` | `SAVEQUERIES` 有効時、全クエリのログ |
| `$show_errors` | `bool` | エラー表示フラグ |
| `$suppress_errors` | `bool` | エラー抑制フラグ |
| `$charset` | `string` | 接続文字セット |
| `$collate` | `string` | 接続照合順序 |

### コアテーブルスキーマ

#### wp_posts

投稿、ページ、添付ファイル、リビジョン、カスタム投稿タイプなど、すべてのコンテンツを格納するメインテーブル。

| カラム | 型 | 説明 |
|---|---|---|
| `ID` | `bigint(20) unsigned` | 投稿 ID（主キー、AUTO_INCREMENT） |
| `post_author` | `bigint(20) unsigned` | 投稿者のユーザー ID |
| `post_date` | `datetime` | 投稿日時（ローカル） |
| `post_date_gmt` | `datetime` | 投稿日時（GMT） |
| `post_content` | `longtext` | 投稿本文 |
| `post_title` | `text` | 投稿タイトル |
| `post_excerpt` | `text` | 投稿の抜粋 |
| `post_status` | `varchar(20)` | 投稿ステータス（`publish`, `draft`, `pending`, `private`, `trash` 等） |
| `comment_status` | `varchar(20)` | コメント許可状態（`open`, `closed`） |
| `ping_status` | `varchar(20)` | ピンバック許可状態（`open`, `closed`） |
| `post_password` | `varchar(255)` | パスワード保護用パスワード |
| `post_name` | `varchar(200)` | スラッグ（URL用） |
| `to_ping` | `text` | ピンバック送信先 URL |
| `pinged` | `text` | ピンバック済み URL |
| `post_modified` | `datetime` | 最終更新日時（ローカル） |
| `post_modified_gmt` | `datetime` | 最終更新日時（GMT） |
| `post_content_filtered` | `longtext` | フィルタ済みコンテンツ |
| `post_parent` | `bigint(20) unsigned` | 親投稿の ID |
| `guid` | `varchar(255)` | グローバル一意識別子 |
| `menu_order` | `int(11)` | メニュー表示順 |
| `post_type` | `varchar(20)` | 投稿タイプ（`post`, `page`, `attachment`, `revision` 等） |
| `post_mime_type` | `varchar(100)` | MIME タイプ（添付ファイル用） |
| `comment_count` | `bigint(20)` | コメント数キャッシュ |

**インデックス:**
- `PRIMARY KEY (ID)`
- `KEY post_name (post_name(191))`
- `KEY type_status_date (post_type, post_status, post_date, ID)`
- `KEY post_parent (post_parent)`
- `KEY post_author (post_author)`

#### wp_postmeta

投稿のカスタムフィールド（メタデータ）を格納する EAV（Entity-Attribute-Value）テーブル。

| カラム | 型 | 説明 |
|---|---|---|
| `meta_id` | `bigint(20) unsigned` | メタ ID（主キー、AUTO_INCREMENT） |
| `post_id` | `bigint(20) unsigned` | 関連する投稿の ID |
| `meta_key` | `varchar(255)` | メタデータのキー |
| `meta_value` | `longtext` | メタデータの値 |

**インデックス:**
- `PRIMARY KEY (meta_id)`
- `KEY post_id (post_id)`
- `KEY meta_key (meta_key(191))`

#### wp_comments

| カラム | 型 | 説明 |
|---|---|---|
| `comment_ID` | `bigint(20) unsigned` | コメント ID（主キー、AUTO_INCREMENT） |
| `comment_post_ID` | `bigint(20) unsigned` | コメント対象の投稿 ID |
| `comment_author` | `tinytext` | コメント投稿者名 |
| `comment_author_email` | `varchar(100)` | コメント投稿者メールアドレス |
| `comment_author_url` | `varchar(200)` | コメント投稿者 URL |
| `comment_author_IP` | `varchar(100)` | コメント投稿者 IP アドレス |
| `comment_date` | `datetime` | コメント日時（ローカル） |
| `comment_date_gmt` | `datetime` | コメント日時（GMT） |
| `comment_content` | `text` | コメント本文 |
| `comment_karma` | `int(11)` | カルマ値（未使用） |
| `comment_approved` | `varchar(20)` | 承認状態（`0`, `1`, `spam`, `trash`） |
| `comment_agent` | `varchar(255)` | コメント投稿者のユーザーエージェント |
| `comment_type` | `varchar(20)` | コメントタイプ（`comment`, `pingback`, `trackback` 等） |
| `comment_parent` | `bigint(20) unsigned` | 親コメントの ID（スレッド表示用） |
| `user_id` | `bigint(20) unsigned` | ログインユーザーの ID（0 = 未ログイン） |

**インデックス:**
- `PRIMARY KEY (comment_ID)`
- `KEY comment_post_ID (comment_post_ID)`
- `KEY comment_approved_date_gmt (comment_approved, comment_date_gmt)`
- `KEY comment_date_gmt (comment_date_gmt)`
- `KEY comment_parent (comment_parent)`
- `KEY comment_author_email (comment_author_email(10))`

#### wp_commentmeta

| カラム | 型 | 説明 |
|---|---|---|
| `meta_id` | `bigint(20) unsigned` | メタ ID（主キー、AUTO_INCREMENT） |
| `comment_id` | `bigint(20) unsigned` | 関連するコメントの ID |
| `meta_key` | `varchar(255)` | メタデータのキー |
| `meta_value` | `longtext` | メタデータの値 |

#### wp_terms

タクソノミーの用語（カテゴリ名、タグ名など）を格納するテーブル。

| カラム | 型 | 説明 |
|---|---|---|
| `term_id` | `bigint(20) unsigned` | 用語 ID（主キー、AUTO_INCREMENT） |
| `name` | `varchar(200)` | 用語名 |
| `slug` | `varchar(200)` | スラッグ |
| `term_group` | `bigint(10)` | 用語グループ（将来使用予定） |

#### wp_termmeta

| カラム | 型 | 説明 |
|---|---|---|
| `meta_id` | `bigint(20) unsigned` | メタ ID（主キー、AUTO_INCREMENT） |
| `term_id` | `bigint(20) unsigned` | 関連する用語の ID |
| `meta_key` | `varchar(255)` | メタデータのキー |
| `meta_value` | `longtext` | メタデータの値 |

#### wp_term_taxonomy

用語をタクソノミーに関連付けるテーブル。同じ用語名でも異なるタクソノミー（カテゴリとタグ）に属することができます。

| カラム | 型 | 説明 |
|---|---|---|
| `term_taxonomy_id` | `bigint(20) unsigned` | 用語タクソノミー ID（主キー、AUTO_INCREMENT） |
| `term_id` | `bigint(20) unsigned` | 用語 ID（`wp_terms.term_id` への外部キー） |
| `taxonomy` | `varchar(32)` | タクソノミー名（`category`, `post_tag`, `link_category` 等） |
| `description` | `longtext` | 説明 |
| `parent` | `bigint(20) unsigned` | 親用語の `term_id`（階層タクソノミー用） |
| `count` | `bigint(20)` | この用語に属するオブジェクトの数 |

**インデックス:**
- `PRIMARY KEY (term_taxonomy_id)`
- `UNIQUE KEY term_id_taxonomy (term_id, taxonomy)`
- `KEY taxonomy (taxonomy)`

#### wp_term_relationships

オブジェクト（投稿など）と用語の多対多リレーションを管理するテーブル。

| カラム | 型 | 説明 |
|---|---|---|
| `object_id` | `bigint(20) unsigned` | オブジェクト ID（通常は投稿 ID） |
| `term_taxonomy_id` | `bigint(20) unsigned` | 用語タクソノミー ID |
| `term_order` | `int(11)` | 表示順 |

**インデックス:**
- `PRIMARY KEY (object_id, term_taxonomy_id)`
- `KEY term_taxonomy_id (term_taxonomy_id)`

#### wp_options

| カラム | 型 | 説明 |
|---|---|---|
| `option_id` | `bigint(20) unsigned` | オプション ID（主キー、AUTO_INCREMENT） |
| `option_name` | `varchar(191)` | オプション名（ユニーク） |
| `option_value` | `longtext` | オプション値 |
| `autoload` | `varchar(20)` | 自動読み込みフラグ（`yes`, `no`, `on`, `off`, `auto`, `auto-on`, `auto-off`） |

**インデックス:**
- `PRIMARY KEY (option_id)`
- `UNIQUE KEY option_name (option_name)`
- `KEY autoload (autoload)`

## 3. API リファレンス

### セキュリティ: prepare()

`prepare()` はプリペアドステートメントを使用して SQL インジェクションを防止します。すべてのユーザー入力を含むクエリで使用が必須です。

```php
$wpdb->prepare(
    string $query,
    mixed  ...$args
): string|void
```

**プレースホルダー:**

| プレースホルダー | 型 | 説明 |
|---|---|---|
| `%s` | `string` | 文字列（自動エスケープ・引用符付き） |
| `%d` | `int` | 整数 |
| `%f` | `float` | 浮動小数点数 |
| `%i` | `identifier` | テーブル名・カラム名（バッククォート付き） |
| `%%` | リテラル | リテラル `%` |

```php
$wpdb->prepare(
    "SELECT * FROM %i WHERE post_status = %s AND post_author = %d",
    $wpdb->posts,
    'publish',
    42
);
```

> **注意**: `%i` プレースホルダーは WordPress 6.2 で導入されました。テーブル名やカラム名を動的に指定する場合に使用します。

### データ取得 API

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `get_var()` | `(?string $query = null, int $x = 0, int $y = 0): string\|null` | 単一の値を取得 |
| `get_row()` | `(?string $query = null, string $output = OBJECT, int $y = 0): object\|array\|null` | 1 行を取得 |
| `get_col()` | `(?string $query = null, int $x = 0): array` | 1 カラムを配列として取得 |
| `get_results()` | `(?string $query = null, string $output = OBJECT): object[]\|array[]\|null` | 複数行を取得 |

**`$output` パラメータ:**

| 定数 | 説明 |
|---|---|
| `OBJECT` | オブジェクトの配列（デフォルト） |
| `OBJECT_K` | カラム値をキーとしたオブジェクトの連想配列 |
| `ARRAY_A` | 連想配列の配列 |
| `ARRAY_N` | 数値添字配列の配列 |

```php
// 単一値
$count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'");

// 1 行（オブジェクト）
$post = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", 42)
);

// 1 カラム
$ids = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type = 'post'");

// 複数行
$results = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_author = %d", 1),
    ARRAY_A
);
```

### データ操作 API

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `query()` | `(string $query): int\|bool` | 任意の SQL を実行 |
| `insert()` | `(string $table, array $data, array\|string $format = null): int\|false` | 行を挿入 |
| `replace()` | `(string $table, array $data, array\|string $format = null): int\|false` | 行を挿入または置換 |
| `update()` | `(string $table, array $data, array $where, array\|string $format = null, array\|string $where_format = null): int\|false` | 行を更新 |
| `delete()` | `(string $table, array $where, array\|string $where_format = null): int\|false` | 行を削除 |

`insert()`, `replace()`, `update()`, `delete()` は内部でエスケープを行うため、`prepare()` と併用する必要はありません。

```php
// INSERT
$wpdb->insert(
    $wpdb->postmeta,
    [
        'post_id'    => 42,
        'meta_key'   => '_custom_field',
        'meta_value' => 'hello',
    ],
    ['%d', '%s', '%s']
);
// $wpdb->insert_id で生成された ID を取得

// UPDATE
$wpdb->update(
    $wpdb->posts,
    ['post_title' => 'New Title'],        // SET
    ['ID' => 42],                         // WHERE
    ['%s'],                               // SET のフォーマット
    ['%d']                                // WHERE のフォーマット
);

// DELETE
$wpdb->delete(
    $wpdb->postmeta,
    ['post_id' => 42, 'meta_key' => '_custom_field'],
    ['%d', '%s']
);

// REPLACE（主キーまたはユニークキーが一致すれば更新、なければ挿入）
$wpdb->replace(
    $wpdb->options,
    [
        'option_name'  => 'my_option',
        'option_value' => 'my_value',
        'autoload'     => 'yes',
    ],
    ['%s', '%s', '%s']
);
```

### テーブル管理 API

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `get_charset_collate()` | `(): string` | 現在の文字セット・照合順序の SQL 句を返す |
| `has_cap()` | `(string $db_cap): bool` | データベース機能の確認 |
| `tables()` | `(string $scope = 'all', bool $prefix = true, int $blog_id = 0): string[]` | テーブル名の一覧を取得 |

### エラーハンドリング API

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `show_errors()` | `(bool $show = true): void` | エラー表示を有効化 |
| `hide_errors()` | `(): void` | エラー表示を無効化 |
| `suppress_errors()` | `(bool $suppress = true): bool` | エラーの抑制を切り替え |
| `print_error()` | `(string $str = ''): false\|void` | エラーメッセージを出力 |
| `last_error` | プロパティ | 最後のエラーメッセージ |

### トランザクション

`wpdb` にはトランザクション専用のメソッドはありませんが、`query()` メソッドを使用してトランザクションを実行できます:

```php
$wpdb->query('START TRANSACTION');

try {
    $wpdb->insert($wpdb->posts, [...]);
    $wpdb->insert($wpdb->postmeta, [...]);
    $wpdb->query('COMMIT');
} catch (\Exception $e) {
    $wpdb->query('ROLLBACK');
}
```

## 4. 実行フロー

### クエリ実行フロー（`query()` メソッド）

```
$wpdb->query($sql)
│
├── $this->flush()                       // 前回の結果をクリア
│   ├── $this->last_result = []
│   ├── $this->last_query = null
│   ├── $this->rows_affected = 0
│   ├── $this->num_rows = 0
│   ├── $this->last_error = ''
│   └── $this->col_info = null
│
├── apply_filters('query', $sql)         // クエリをフィルタリング
│
├── $this->_do_query($sql)
│   ├── $this->timer_start()
│   ├── mysqli_real_query() 実行
│   └── $this->timer_stop()
│
├── SAVEQUERIES 有効時
│   └── $this->queries[] にクエリ、実行時間、コールスタックを記録
│
├── エラーチェック
│   ├── エラーあり: $this->last_error に設定
│   └── エラーなし: 結果をフェッチ
│       ├── SELECT: $this->last_result にオブジェクト配列を格納
│       │          $this->num_rows = 結果行数
│       └── INSERT/UPDATE/DELETE:
│           $this->rows_affected = 影響行数
│           $this->insert_id = AUTO_INCREMENT 値
│
├── $this->num_queries++                 // クエリカウンタ
│
└── return $this->rows_affected or $this->num_rows
```

### `insert()` メソッドの内部フロー

```
$wpdb->insert($table, $data, $format)
│
├── $wpdb->_insert_replace_helper($table, $data, $format, 'INSERT')
│   │
│   ├── $data の各値を $format に基づきフォーマット
│   │   '%s' → $wpdb->prepare('%s', $value)
│   │   '%d' → $wpdb->prepare('%d', $value)
│   │   '%f' → $wpdb->prepare('%f', $value)
│   │
│   ├── SQL 構築: "INSERT INTO `$table` (`col1`, `col2`) VALUES (%s, %d)"
│   │
│   └── $wpdb->query($sql)
│
└── return 成功時: 影響行数, 失敗時: false
```

### `prepare()` のプレースホルダー処理

```
$wpdb->prepare("SELECT * FROM %i WHERE ID = %d AND name = %s", $table, 42, "test")
│
├── プレースホルダーのパース
│   ├── %i → バッククォート付きの識別子: `wp_posts`
│   ├── %d → 整数値: 42
│   ├── %s → エスケープ済み文字列: 'test'
│   └── %% → リテラル %
│
├── vsprintf() でフォーマット（リテラル %% を保護した上で）
│
└── return "SELECT * FROM `wp_posts` WHERE ID = 42 AND name = 'test'"
```

## 5. マルチサイト対応

### テーブルスコープ

WordPress マルチサイト環境では、テーブルは 2 つのスコープに分類されます:

| スコープ | テーブル | 説明 |
|---|---|---|
| グローバル | `wp_users`, `wp_usermeta`, `wp_blogs`, `wp_site`, `wp_sitemeta`, `wp_signups`, `wp_registration_log` | ネットワーク全体で共有 |
| サイト固有 | `wp_N_posts`, `wp_N_postmeta`, `wp_N_comments`, `wp_N_options` 等 | サイトごとに独立（N = ブログ ID） |

### `$wpdb->set_prefix()`

マルチサイト環境でブログを切り替える際、`switch_to_blog()` は内部的に `$wpdb->set_prefix()` を呼び出してテーブルプレフィックスを更新します。

```php
// ブログ ID 3 のテーブル
$wpdb->set_prefix('wp_3_');
// $wpdb->posts → 'wp_3_posts'
// $wpdb->options → 'wp_3_options'
// グローバルテーブルは変更されない
// $wpdb->users → 'wp_users'
```

## 6. フック一覧

### Filter

| フック名 | シグネチャ | 説明 |
|---|---|---|
| `query` | `(string $query): string` | 実行前のクエリ文字列をフィルタリング |
| `wpdb_pre_query` | `(string\|null $result, string $query): string\|null` | クエリ実行前にショートサーキット可能 |
| `log_query_custom_data` | `(array $query_data, string $query, float $query_time, string $query_callstack, float $query_start): array` | SAVEQUERIES のカスタムデータ |

### デバッグ

`SAVEQUERIES` を有効にすると、すべてのクエリが `$wpdb->queries` に記録されます:

```php
// wp-config.php
define('SAVEQUERIES', true);

// 使用例
foreach ($wpdb->queries as $query_info) {
    [$sql, $elapsed_time, $caller] = $query_info;
}
```

## 7. `dbDelta()` によるスキーマ管理

プラグインやテーマが独自のテーブルを作成・更新する場合、`dbDelta()` 関数（`wp-admin/includes/upgrade.php`）を使用します。

```php
dbDelta(string|string[] $queries = '', bool $execute = true): array
```

`dbDelta()` は CREATE TABLE 文を解析し、既存のテーブルとの差分を検出して ALTER TABLE 文を生成します。新しいカラムの追加やインデックスの変更は自動的に行われますが、カラムの削除は行いません。

```php
global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE {$wpdb->prefix}custom_table (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(200) NOT NULL DEFAULT '',
    value longtext NOT NULL,
    PRIMARY KEY  (id),
    KEY name (name(191))
) $charset_collate;";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta($sql);
```

> **注意**: `dbDelta()` は SQL のフォーマットに厳格です。`PRIMARY KEY` の後には 2 つのスペースが必要で、各カラム定義は独自の行に記述する必要があります。
