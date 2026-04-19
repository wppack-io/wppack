# WordPress Object Cache ドロップイン仕様

## 概要

WordPress の Object Cache ドロップインは、`wp-content/object-cache.php` に配置することで WordPress のオブジェクトキャッシュバックエンドを置き換える仕組みです。デフォルトの `WP_Object_Cache` クラス（リクエスト内メモリのみ）を Redis / Valkey / DynamoDB / APCu 等の永続キャッシュに差し替えることができます。

## WordPress のドロップイン読み込みフロー

### 読み込みタイミング

WordPress は `wp-settings.php` の非常に早い段階で Object Cache を初期化します:

1. `wp-settings.php` が `ABSPATH . 'wp-includes/cache.php'` を読み込む
2. `cache.php` 内で `WP_CONTENT_DIR . '/object-cache.php'` の存在をチェック
3. **ドロップインが存在する場合**: `object-cache.php` を読み込み、`wp_cache_init()` を呼び出す
4. **ドロップインが存在しない場合**: デフォルトの `WP_Object_Cache` クラスを使用し、`wp_cache_init()` で `$wp_object_cache = new WP_Object_Cache()` を実行

この初期化はプラグインやテーマの読み込みよりも前に行われます。

### WP_Object_Cache のデフォルト実装

WordPress デフォルトの `WP_Object_Cache` はリクエスト内メモリのみでキャッシュを保持します:
- PHP 配列にデータを格納
- リクエスト終了時にすべてのデータが破棄される
- 永続化なし（Redis 等のドロップインで補完する設計）

## ドロップインの要件

### 定義すべき関数

ドロップインは以下の `wp_cache_*()` 関数を定義する必要があります。これらの関数は WordPress コアおよびプラグインから呼び出されます。

#### 必須関数

| 関数 | シグネチャ | 説明 |
|------|-----------|------|
| `wp_cache_init` | `(): void` | キャッシュバックエンドの初期化。`$GLOBALS['wp_object_cache']` を設定 |
| `wp_cache_get` | `(string $key, string $group = '', bool $force = false, bool &$found = false): mixed` | キャッシュ値の取得 |
| `wp_cache_set` | `(string $key, mixed $data, string $group = '', int $expire = 0): bool` | キャッシュ値の設定 |
| `wp_cache_add` | `(string $key, mixed $data, string $group = '', int $expire = 0): bool` | 新規追加（既存キーがあれば失敗） |
| `wp_cache_replace` | `(string $key, mixed $data, string $group = '', int $expire = 0): bool` | 既存キーの置換（存在しなければ失敗） |
| `wp_cache_delete` | `(string $key, string $group = ''): bool` | キャッシュ値の削除 |
| `wp_cache_flush` | `(): bool` | 全キャッシュのクリア |
| `wp_cache_close` | `(): bool` | 接続のクローズ |
| `wp_cache_add_global_groups` | `(string\|array $groups): void` | グローバルグループの登録 |
| `wp_cache_add_non_persistent_groups` | `(string\|array $groups): void` | 非永続グループの登録 |
| `wp_cache_switch_to_blog` | `(int $blogId): void` | マルチサイトでのブログ切り替え |

#### WordPress 6.1+ で追加された関数

| 関数 | シグネチャ | 説明 |
|------|-----------|------|
| `wp_cache_get_multiple` | `(array $keys, string $group = '', bool $force = false): array` | 複数キーの一括取得 |
| `wp_cache_set_multiple` | `(array $data, string $group = '', int $expire = 0): array` | 複数キーの一括設定 |
| `wp_cache_add_multiple` | `(array $data, string $group = '', int $expire = 0): array` | 複数キーの一括追加 |
| `wp_cache_delete_multiple` | `(array $keys, string $group = ''): array` | 複数キーの一括削除 |
| `wp_cache_flush_runtime` | `(): bool` | ランタイムキャッシュのみクリア |
| `wp_cache_flush_group` | `(string $group): bool` | グループ単位のクリア |
| `wp_cache_supports` | `(string $feature): bool` | 機能サポートの確認 |
| `wp_cache_incr` | `(string $key, int $offset = 1, string $group = ''): int\|false` | 数値のインクリメント |
| `wp_cache_decr` | `(string $key, int $offset = 1, string $group = ''): int\|false` | 数値のデクリメント |

### `wp_cache_supports()` で宣言可能な機能

| 機能名 | 説明 |
|--------|------|
| `add_multiple` | `wp_cache_add_multiple()` をサポート |
| `set_multiple` | `wp_cache_set_multiple()` をサポート |
| `get_multiple` | `wp_cache_get_multiple()` をサポート |
| `delete_multiple` | `wp_cache_delete_multiple()` をサポート |
| `flush_runtime` | `wp_cache_flush_runtime()` をサポート |
| `flush_group` | `wp_cache_flush_group()` をサポート |
| `hash_alloptions` | `alloptions` 等の大きなキーを Redis Hash に格納（`HashableAdapterInterface` 対応アダプタ + `WPPACK_CACHE_HASH_ALLOPTIONS` 有効時） |

## グループ管理

### キャッシュグループ

WordPress のオブジェクトキャッシュはグループでキーを分類します。同じキー名でもグループが異なれば別のエントリとして扱われます。

```php
wp_cache_set('key', 'value1', 'group_a');
wp_cache_set('key', 'value2', 'group_b');
// group_a:key と group_b:key は別エントリ
```

### グローバルグループ

`wp_cache_add_global_groups()` で登録されたグループは、マルチサイト環境で全サイト共通のキャッシュ空間を使用します。WordPress コアは以下をグローバルグループとして登録します:

- `blog-details`, `blog-id-cache`, `blog-lookup`
- `global-posts`, `users`, `useremail`, `userlogins`, `usermeta`, `user_meta`, `userslugs`
- `site-transient`, `site-options`, `site-lookup`, `blog_meta`
- `networks`, `rss`, `themes`, `counts`

### 非永続グループ

`wp_cache_add_non_persistent_groups()` で登録されたグループは、永続キャッシュバックエンドに書き込まれず、リクエスト内メモリのみで動作します。リクエストごとに計算し直す必要があるデータに使用されます。

## マルチサイトでのキープレフィックス

マルチサイト環境では、各サイトのキャッシュデータを分離するためにブログ ID をキーのプレフィックスに含めます:

```
{prefix}{blogId}:{group}:{key}
```

- **通常のグループ**: 現在のブログ ID を使用
- **グローバルグループ**: ブログ ID は `0`（固定）

`wp_cache_switch_to_blog()` が呼ばれると、以降のキャッシュ操作は新しいブログ ID をプレフィックスに使用します。

## ドロップインの配置と確認

### 配置方法

```bash
# WPPack の場合
cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php
```

### 動作確認

```bash
# WP-CLI でキャッシュタイプを確認
wp cache type

# キャッシュの動作テスト
wp cache set test_key test_value
wp cache get test_key
# => test_value
```

### WordPress 管理画面での確認

管理画面 → ツール → サイトヘルス → 情報 → WordPress 定数 セクションで、`WP_CACHE` 定数と有効なドロップインを確認できます。
