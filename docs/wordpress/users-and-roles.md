# WordPress ユーザー・ロール・権限仕様

## 1. 概要

WordPress のユーザーシステムは、ユーザーアカウント管理、ロール（役割）ベースのアクセス制御、きめ細かな権限（Capability）チェックの 3 層で構成されています。

主要なクラスとグローバル変数:

| クラス / グローバル変数 | 説明 |
|---|---|
| `WP_User` | ユーザーオブジェクト。DB データとロール・権限を統合 |
| `WP_Roles` | ロール定義の管理。`wp_options` に永続化 |
| `WP_Role` | 個別のロール（名前 + 権限の集合） |
| `$wp_roles` | `WP_Roles` のグローバルインスタンス |
| `$current_user` | 現在ログイン中の `WP_User`（`wp_get_current_user()` でアクセス） |

## 2. データ構造

### DB テーブル

#### `wp_users` テーブル

| カラム | 型 | 説明 |
|---|---|---|
| `ID` | `bigint(20) unsigned` | ユーザー ID（主キー） |
| `user_login` | `varchar(60)` | ログイン名（ユニーク） |
| `user_pass` | `varchar(255)` | パスワードハッシュ（phpass） |
| `user_nicename` | `varchar(50)` | URL に使用されるスラッグ |
| `user_email` | `varchar(100)` | メールアドレス |
| `user_url` | `varchar(100)` | ウェブサイト URL |
| `user_registered` | `datetime` | 登録日時 |
| `user_activation_key` | `varchar(255)` | パスワードリセット用キー |
| `user_status` | `int(11)` | ステータス（非推奨、常に 0） |
| `display_name` | `varchar(250)` | 表示名 |

インデックス: `user_login_key`（user_login）、`user_nicename`（user_nicename）、`user_email`（user_email）

#### `wp_usermeta` テーブル

| カラム | 型 | 説明 |
|---|---|---|
| `umeta_id` | `bigint(20) unsigned` | メタ ID（主キー） |
| `user_id` | `bigint(20) unsigned` | ユーザー ID |
| `meta_key` | `varchar(255)` | メタキー |
| `meta_value` | `longtext` | メタ値（シリアライズ可能） |

インデックス: `user_id`（user_id）、`meta_key`（meta_key(191)）

#### 重要なユーザーメタキー

| メタキー | 説明 |
|---|---|
| `{prefix}capabilities` | ロールと直接割り当て権限の連想配列（シリアライズ） |
| `{prefix}user_level` | 後方互換用のユーザーレベル（0-10、非推奨） |
| `nickname` | ニックネーム |
| `first_name` | 名 |
| `last_name` | 姓 |
| `description` | プロフィール説明 |
| `rich_editing` | ビジュアルエディター使用フラグ |
| `syntax_highlighting` | シンタックスハイライト使用フラグ |
| `session_tokens` | セッショントークン（シリアライズ） |

`{prefix}` はテーブルプレフィックス（デフォルト `wp_`）。マルチサイトではサイトごとに異なるプレフィックスを使用します（例: `wp_2_capabilities`）。

### `wp_capabilities` メタの構造

```php
// ユーザーメタに保存される形式
[
    'administrator' => true,  // ロール名 => true
    'edit_pages'    => true,  // 直接割り当ての権限
]
```

ロール名をキーに `true` を値として保持します。ユーザーは複数のロールを持つことができ、さらにロール外の個別権限も直接割り当て可能です。

### WP_User クラス

```php
class WP_User {
    public $data;           // stdClass - wp_users テーブルの行データ
    public int $ID;
    public array $caps      = [];  // ユーザー直接の権限 + ロール名
    public array $roles     = [];  // 割り当てられたロール名の配列
    public array $allcaps   = [];  // 全権限の統合結果（ロール + 直接）

    // マジックプロパティで以下にアクセス可能
    // user_login, user_pass, user_nicename, user_email, user_url,
    // user_registered, user_activation_key, user_status, display_name
}
```

### `$allcaps` の構築

`WP_User::get_role_caps()` が呼ばれると、以下の手順で `$allcaps` を構築します:

1. `$caps`（ユーザーメタの `{prefix}capabilities`）を読み込み
2. `$caps` のキーからロール名を `$roles` に抽出
3. 各ロールの権限を `$allcaps` にマージ
4. `$caps` の直接権限を `$allcaps` に上書きマージ

```php
// 結果例（administrator ロールのユーザー）
$allcaps = [
    'switch_themes'          => true,
    'edit_themes'            => true,
    'activate_plugins'       => true,
    'edit_plugins'           => true,
    'edit_users'             => true,
    'edit_posts'             => true,
    // ... administrator の全権限
    'administrator'          => true,  // ロール名も含まれる
];
```

### WP_Roles クラス

```php
class WP_Roles {
    public array $roles;        // ロール定義の配列
    public array $role_objects;  // WP_Role オブジェクトの配列
    public array $role_names;    // ロール名 => 表示名のマッピング
    protected string $role_key;  // options テーブルのキー（{prefix}user_roles）
}
```

### ロール定義の永続化

ロールは `wp_options` テーブルの `{prefix}user_roles` オプションにシリアライズして保存されます:

```php
// wp_options に保存される構造
[
    'administrator' => [
        'name'         => 'Administrator',
        'capabilities' => [
            'switch_themes'     => true,
            'edit_themes'       => true,
            'activate_plugins'  => true,
            // ...
        ],
    ],
    'editor' => [
        'name'         => 'Editor',
        'capabilities' => [
            'moderate_comments' => true,
            'manage_categories' => true,
            'edit_others_posts' => true,
            // ...
        ],
    ],
    // subscriber, contributor, author ...
];
```

### デフォルトロール

| ロール | 説明 | 主な権限 |
|---|---|---|
| `administrator` | 管理者 | 全権限 |
| `editor` | 編集者 | 全投稿の編集・公開・削除、コメント管理 |
| `author` | 投稿者 | 自分の投稿の編集・公開・削除 |
| `contributor` | 寄稿者 | 自分の投稿の編集（公開不可） |
| `subscriber` | 購読者 | 閲覧のみ（`read` 権限） |

## 3. API リファレンス

### ユーザー CRUD

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_insert_user()` | `(array\|object\|WP_User $userdata): int\|WP_Error` | ユーザーの作成 |
| `wp_update_user()` | `(array\|object\|WP_User $userdata): int\|WP_Error` | ユーザーの更新 |
| `wp_delete_user()` | `(int $id, ?int $reassign = null): bool` | ユーザーの削除 |
| `wp_create_user()` | `(string $username, string $password, string $email = ''): int\|WP_Error` | 簡易ユーザー作成 |
| `get_userdata()` | `(int $user_id): WP_User\|false` | ユーザーデータの取得 |
| `get_user_by()` | `(string $field, int\|string $value): WP_User\|false` | フィールド指定で取得 |
| `get_users()` | `(array $args = []): array` | ユーザー一覧の取得（WP_User_Query ラッパー） |
| `username_exists()` | `(string $username): int\|false` | ユーザー名の存在確認 |
| `email_exists()` | `(string $email): int\|false` | メールアドレスの存在確認 |
| `wp_get_current_user()` | `(): WP_User` | 現在のユーザーを取得 |
| `wp_set_current_user()` | `(int $id, string $name = ''): WP_User` | 現在のユーザーを設定 |

`get_user_by()` の `$field` パラメータ: `'id'`, `'ID'`, `'slug'`, `'email'`, `'login'`

### ユーザーメタ

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_user_meta()` | `(int $user_id, string $key = '', bool $single = false): mixed` | メタデータの取得 |
| `add_user_meta()` | `(int $user_id, string $meta_key, mixed $meta_value, bool $unique = false): int\|false` | メタデータの追加 |
| `update_user_meta()` | `(int $user_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int\|bool` | メタデータの更新 |
| `delete_user_meta()` | `(int $user_id, string $meta_key, mixed $meta_value = ''): bool` | メタデータの削除 |

これらは汎用メタ API（`get_metadata()` / `add_metadata()` 等）のラッパーです。

### パスワード

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_set_password()` | `(string $password, int $user_id): void` | パスワードの設定（DB 直接更新） |
| `wp_hash_password()` | `(string $password): string` | パスワードのハッシュ化（pluggable） |
| `wp_check_password()` | `(string $password, string $hash, int\|string $user_id = ''): bool` | パスワードの検証（pluggable） |
| `wp_generate_password()` | `(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string` | ランダムパスワード生成 |

`wp_hash_password()` は `PasswordHash`（phpass）を使用し、`wp_check_password()` は phpass ハッシュに加え、古い MD5 ハッシュも後方互換で検証します。

### 認証

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `wp_authenticate()` | `(string $username, string $password): WP_User\|WP_Error` | ユーザー認証（pluggable） |
| `wp_signon()` | `(array $credentials = [], bool $secure_cookie = ''): WP_User\|WP_Error` | ログイン処理 |
| `wp_logout()` | `(): void` | ログアウト処理 |
| `wp_validate_auth_cookie()` | `(string $cookie = '', string $scheme = ''): int\|false` | 認証クッキーの検証（pluggable） |
| `wp_set_auth_cookie()` | `(int $user_id, bool $remember = false, bool\|string $secure = '', string $token = ''): void` | 認証クッキーの設定（pluggable） |
| `wp_clear_auth_cookie()` | `(): void` | 認証クッキーのクリア（pluggable） |
| `is_user_logged_in()` | `(): bool` | ログイン状態の判定 |

### ロール管理

| 関数 / メソッド | シグネチャ | 説明 |
|---|---|---|
| `add_role()` | `(string $role, string $display_name, bool[] $capabilities = []): WP_Role\|null` | ロールの追加 |
| `remove_role()` | `(string $role): void` | ロールの削除 |
| `get_role()` | `(string $role): WP_Role\|null` | ロールの取得 |
| `WP_Roles::add_role()` | `(string $role, string $display_name, bool[] $capabilities = []): WP_Role\|void` | ロールの追加（インスタンスメソッド） |
| `WP_Roles::remove_role()` | `(string $role): void` | ロールの削除 |
| `WP_Roles::add_cap()` | `(string $role, string $cap, bool $grant = true): void` | ロールに権限を追加 |
| `WP_Roles::remove_cap()` | `(string $role, string $cap): void` | ロールから権限を削除 |
| `WP_Roles::get_names()` | `(): string[]` | ロール名一覧 |

グローバル関数 `add_role()` / `remove_role()` / `get_role()` は `$wp_roles` インスタンスへの委譲です。

### ユーザーのロール操作

| メソッド | シグネチャ | 説明 |
|---|---|---|
| `WP_User::add_role()` | `(string $role): void` | ユーザーにロールを追加 |
| `WP_User::remove_role()` | `(string $role): void` | ユーザーからロールを削除 |
| `WP_User::set_role()` | `(string $role): void` | ユーザーのロールを置換（既存ロールをすべて削除して設定） |
| `WP_User::add_cap()` | `(string $cap, bool $grant = true): void` | 直接権限を追加 |
| `WP_User::remove_cap()` | `(string $cap): void` | 直接権限を削除 |
| `WP_User::remove_all_caps()` | `(): void` | 全ロール・権限を削除 |
| `WP_User::has_cap()` | `(string $cap, mixed ...$args): bool` | 権限チェック |

### 権限チェック

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `current_user_can()` | `(string $capability, mixed ...$args): bool` | 現在のユーザーの権限チェック |
| `current_user_can_for_blog()` | `(int $blog_id, string $capability, mixed ...$args): bool` | 指定ブログでの権限チェック |
| `user_can()` | `(int\|WP_User $user, string $capability, mixed ...$args): bool` | 指定ユーザーの権限チェック |
| `author_can()` | `(int\|WP_Post $post, string $capability, mixed ...$args): bool` | 投稿者の権限チェック |
| `map_meta_cap()` | `(string $cap, int $user_id, mixed ...$args): string[]` | メタ権限をプリミティブ権限にマッピング |

### WP_User_Query

| パラメータ | 型 | 説明 |
|---|---|---|
| `role` | `string\|string[]` | ロールでフィルタ |
| `role__in` | `string[]` | いずれかのロールを持つユーザー |
| `role__not_in` | `string[]` | いずれのロールも持たないユーザー |
| `capability` | `string\|string[]` | 権限でフィルタ（5.9+） |
| `capability__in` | `string[]` | いずれかの権限を持つユーザー（5.9+） |
| `capability__not_in` | `string[]` | いずれの権限も持たないユーザー（5.9+） |
| `include` | `int[]` | 含めるユーザー ID |
| `exclude` | `int[]` | 除外するユーザー ID |
| `search` | `string` | 検索文字列（`*` ワイルドカード対応） |
| `search_columns` | `string[]` | 検索対象カラム |
| `meta_key` | `string` | メタキーで絞り込み |
| `meta_value` | `string` | メタ値で絞り込み |
| `meta_query` | `array` | 複合メタクエリ |
| `orderby` | `string\|array` | ソート基準（`ID`, `login`, `nicename`, `email`, `registered`, `display_name`, `meta_value` 等） |
| `order` | `string` | ソート方向（`ASC` / `DESC`） |
| `number` | `int` | 取得件数 |
| `offset` | `int` | オフセット |
| `paged` | `int` | ページ番号 |
| `count_total` | `bool` | 総件数を計算するか（デフォルト `true`） |
| `fields` | `string\|string[]` | 取得フィールド（`'all'`, `'ID'`, `'display_name'` 等） |
| `blog_id` | `int` | マルチサイトでのブログ ID |
| `has_published_posts` | `bool\|string[]` | 公開済み投稿を持つユーザー |

## 4. 実行フロー

### 権限チェックのフロー（`current_user_can()`）

```
current_user_can('edit_post', $post_id)
│
├── wp_get_current_user() で現在のユーザーを取得
│
├── WP_User::has_cap('edit_post', $post_id)
│   │
│   ├── map_meta_cap('edit_post', $user_id, $post_id)
│   │   │
│   │   ├── 投稿を取得
│   │   ├── 投稿者が本人か判定
│   │   │   ├── 本人の場合 → ['edit_posts'] を返す
│   │   │   └── 他人の場合 → ['edit_others_posts'] を返す
│   │   └── 投稿タイプの権限マッピングを適用
│   │
│   ├── apply_filters('map_meta_cap', $caps, $cap, $user_id, $args)
│   │
│   ├── 返されたプリミティブ権限をすべてチェック
│   │   └── foreach ($caps as $cap) → $allcaps[$cap] が true か確認
│   │
│   ├── apply_filters('user_has_cap', $allcaps, $caps, $args, $user)
│   │
│   └── 全プリミティブ権限が true なら true を返す
│
└── return bool
```

### メタ権限とプリミティブ権限

WordPress の権限は 2 種類あります:

- **プリミティブ権限**: ロールに直接割り当てられる権限（`edit_posts`, `manage_options` 等）
- **メタ権限**: コンテキスト依存の権限（`edit_post`, `delete_user` 等）。`map_meta_cap()` でプリミティブ権限に変換される

```
メタ権限                    → map_meta_cap() → プリミティブ権限
edit_post ($post_id)       →                → edit_posts（自分の投稿）
                                              edit_others_posts（他人の投稿）
                                              edit_published_posts（公開済み）
delete_user ($user_id)     →                → delete_users
                                              + 対象が admin なら do_not_allow
read_post ($post_id)       →                → read（公開済み）
                                              read_private_posts（非公開）
```

### ユーザー作成のフロー（`wp_insert_user()`）

```
wp_insert_user($userdata)
│
├── データのサニタイズ
│   ├── user_login のバリデーション
│   ├── user_email の重複チェック
│   ├── user_nicename のスラッグ生成
│   └── パスワードのハッシュ化（wp_hash_password()）
│
├── apply_filters('pre_user_login', $user_login)
├── apply_filters('pre_user_nicename', $user_nicename)
├── apply_filters('pre_user_email', $user_email)
├── apply_filters('pre_user_url', $user_url)
├── apply_filters('pre_user_display_name', $display_name)
│
├── $wpdb->insert() / $wpdb->update()  // wp_users テーブル
│
├── ユーザーメタの保存
│   ├── {prefix}capabilities（ロール）
│   ├── nickname, first_name, last_name 等
│   └── その他のメタデータ
│
├── wp_cache_delete($user_id, 'users')  // キャッシュクリア
│
├── 新規作成の場合:
│   ├── do_action('user_register', $user_id, $userdata)
│   └── do_action('wp_after_insert_user', $user, true, $userdata)  // WP 6.7+
│
├── 更新の場合:
│   ├── do_action('profile_update', $user_id, $old_user_data, $userdata)
│   └── do_action('wp_after_insert_user', $user, false, $userdata)  // WP 6.7+
│
└── return $user_id
```

### 認証のフロー（`wp_authenticate()`）

```
wp_authenticate($username, $password)
│
├── apply_filters('authenticate', null, $username, $password)
│   │
│   │  デフォルトのフィルター（優先度順）:
│   ├── [20] wp_authenticate_username_password()
│   │   ├── get_user_by('login', $username) / get_user_by('email', $username)
│   │   └── wp_check_password($password, $user->user_pass, $user->ID)
│   │
│   ├── [20] wp_authenticate_email_password()
│   │   ├── get_user_by('email', $username)
│   │   └── wp_check_password()
│   │
│   ├── [30] wp_authenticate_cookie()
│   │   └── ログインフォーム以外でのクッキー認証
│   │
│   └── [99] wp_authenticate_spam_check()
│       └── マルチサイトでの spam ユーザーチェック
│
├── apply_filters('authenticate', $user, $username, $password)
│
├── WP_Error の場合:
│   ├── do_action('wp_login_failed', $username, $error)
│   └── return WP_Error
│
└── return WP_User
```

## 5. フック一覧

### Action

| フック名 | 発火タイミング | 引数 |
|---|---|---|
| `user_register` | ユーザー新規作成後 | `$user_id`, `$userdata` |
| `profile_update` | ユーザー更新後 | `$user_id`, `$old_user_data`, `$userdata` |
| `wp_after_insert_user` | 作成・更新両方の後（6.7+） | `$user`, `$update`, `$userdata` |
| `delete_user` | ユーザー削除前 | `$user_id`, `$reassign`, `$user` |
| `deleted_user` | ユーザー削除後 | `$user_id`, `$reassign`, `$user` |
| `wp_login` | ログイン成功後 | `$user_login`, `$user` |
| `wp_logout` | ログアウト時 | `$user_id` |
| `wp_login_failed` | ログイン失敗時 | `$username`, `$error` |
| `set_user_role` | ロール変更時 | `$user_id`, `$role`, `$old_roles` |
| `add_user_role` | ロール追加時（4.3+） | `$user_id`, `$role` |
| `remove_user_role` | ロール削除時（4.3+） | `$user_id`, `$role` |
| `set_current_user` | 現在のユーザー設定時 | *(引数なし)* |
| `grant_super_admin` | Super Admin 権限付与後 | `$user_id` |
| `revoke_super_admin` | Super Admin 権限剥奪後 | `$user_id` |
| `lostpassword_post` | パスワードリセットリクエスト時 | `$errors`, `$user_data` |
| `password_reset` | パスワードリセット実行時 | `$user`, `$new_pass` |
| `retrieve_password_key` | リセットキー生成後 | `$user_login`, `$key` |
| `after_password_reset` | パスワードリセット完了後 | `$user`, `$new_pass` |

### Filter

| フック名 | 説明 | 引数 |
|---|---|---|
| `authenticate` | 認証処理全体 | `$user\|null`, `$username`, `$password` |
| `wp_authenticate_user` | ユーザー認証チェック | `$user`, `$password` |
| `user_has_cap` | 権限チェック結果のフィルタ | `$allcaps`, `$caps`, `$args`, `$user` |
| `map_meta_cap` | メタ権限のマッピング | `$caps`, `$cap`, `$user_id`, `$args` |
| `editable_roles` | 編集可能なロール一覧 | `$all_roles` |
| `pre_user_login` | ユーザー名のフィルタ | `$user_login` |
| `pre_user_nicename` | ニックネームのフィルタ | `$user_nicename` |
| `pre_user_email` | メールアドレスのフィルタ | `$user_email` |
| `pre_user_url` | URL のフィルタ | `$user_url` |
| `pre_user_display_name` | 表示名のフィルタ | `$display_name` |
| `insert_user_meta` | 保存前のユーザーメタ | `$meta`, `$user`, `$update`, `$userdata` |
| `pre_get_users` | WP_User_Query 実行前 | `$query` |
| `found_users_query` | 総件数クエリ | `$sql`, `$query` |
| `users_list_table_query_args` | 管理画面ユーザー一覧のクエリ引数 | `$args` |
| `user_search_columns` | ユーザー検索対象カラム | `$search_columns`, `$search`, `$query` |
| `get_usernumposts` | ユーザーの投稿数 | `$count`, `$user_id` |
| `auth_cookie_expiration` | 認証クッキーの有効期限 | `$expiration`, `$user_id`, `$remember` |
| `check_password` | パスワード検証結果 | `$check`, `$password`, `$hash`, `$user_id` |
| `send_password_change_email` | パスワード変更通知を送信するか | `$send`, `$user`, `$userdata` |
| `send_email_change_email` | メール変更通知を送信するか | `$send`, `$user`, `$userdata` |
| `allow_password_reset` | パスワードリセットを許可するか | `$allow`, `$user_id` |
| `user_registration_email` | 登録メールアドレスのフィルタ | `$user_email` |
| `registration_errors` | 登録エラーのフィルタ | `$errors`, `$sanitized_user_login`, `$user_email` |
| `wp_pre_insert_user_data` | DB 挿入前のユーザーデータ | `$data`, `$update`, `$user_id`, `$userdata` |

## 6. マルチサイトでの動作

### ユーザーとサイトの関係

マルチサイト環境では、ユーザーはネットワーク全体で共有されますが、各サイトへのアクセス権は個別に管理されます:

- `wp_users` テーブルはネットワーク全体で 1 つ
- `wp_usermeta` の `{prefix}capabilities` はサイトごとに異なるプレフィックスを使用
- ユーザーが複数サイトに属する場合、サイトごとに異なるロールを持てる

### Super Admin

マルチサイトでは `Super Admin` という特別な権限があり、`wp_sitemeta` テーブルの `site_admins` オプションにユーザーログイン名のリストとして保存されます。Super Admin は `map_meta_cap()` で `'do_not_allow'` 以外の全権限が付与されます。

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `is_super_admin()` | `(?int $user_id = null): bool` | Super Admin か判定 |
| `grant_super_admin()` | `(int $user_id): bool` | Super Admin 権限を付与 |
| `revoke_super_admin()` | `(int $user_id): bool` | Super Admin 権限を剥奪 |

### サイト関連 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_user_to_blog()` | `(int $blog_id, int $user_id, string $role): true\|WP_Error` | ユーザーをサイトに追加 |
| `remove_user_from_blog()` | `(int $user_id, int $blog_id = 0, int $reassign = 0): true\|WP_Error` | ユーザーをサイトから削除 |
| `is_user_member_of_blog()` | `(int $user_id = 0, int $blog_id = 0): bool` | サイトのメンバーか判定 |
| `get_blogs_of_user()` | `(int $user_id, bool $all = false): WP_Site[]` | ユーザーが所属するサイト一覧 |
