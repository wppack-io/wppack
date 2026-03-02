# Cache コンポーネント

**パッケージ:** `wppack/cache`
**名前空間:** `WpPack\Component\Cache\`
**レイヤー:** Abstraction

WordPress Object Cache API（`wp_cache_get()` / `wp_cache_set()`）のオブジェクト指向ラッパーです。WordPress のオブジェクトキャッシュを型安全かつモダンなインターフェースで操作できます。

## インストール

```bash
composer require wppack/cache
```

## Transient コンポーネントとの違い

| | Cache | Transient |
|---|-------|----------|
| **バックエンド** | WordPress Object Cache API | WordPress Transient API |
| **WordPress API** | `wp_cache_get()` / `wp_cache_set()` | `get_transient()` / `set_transient()` |
| **永続化** | ドロップインに依存（デフォルトはリクエスト内メモリ） | デフォルトはデータベース。ドロップインがあればオブジェクトキャッシュに自動委譲 |
| **有効期限** | 任意（デフォルト 0 = 期限なし） | 任意（0 = 期限なし。常に設定推奨） |
| **グループ** | キャッシュグループをサポート | グループなし |
| **用途** | 高頻度アクセスデータのキャッシュ | 有効期限付きの一時データ保存 |

**使い分けの指針:**
- 高頻度にアクセスするデータ → **Cache**（ドロップインで Redis 等に永続化）
- API レスポンスなど有効期限付きの一時データ → **Transient**
- ドロップインを導入済みの環境 → **Cache** を中心に使用
- ドロップインなしの環境 → **Transient**（データベースに永続化される）

> [!NOTE]
> WordPress は Object Cache ドロップインが有効な場合、Transient API の呼び出しを内部的にオブジェクトキャッシュに委譲します。つまり Redis 等のドロップインを導入すると、Cache コンポーネントも Transient コンポーネントも同じバックエンドを利用することになります。

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - 手動のキャッシュ管理
function get_expensive_data(int $id): mixed {
    $cache_key = 'expensive_data_' . $id;
    $group = 'my_app';
    $cached = wp_cache_get($cache_key, $group);

    if ($cached !== false) {
        return $cached;
    }

    $data = perform_expensive_operation($id);
    wp_cache_set($cache_key, $data, $group, 3600);

    return $data;
}
```

### After（WpPack）

```php
use WpPack\Component\Cache\CacheManager;

final class DataService
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    public function getExpensiveData(int $id): mixed
    {
        $key = "expensive_data_{$id}";
        $cached = $this->cache->get($key, 'my_app');

        if ($cached !== false) {
            return $cached;
        }

        $data = $this->performExpensiveOperation($id);
        $this->cache->set($key, $data, 'my_app', 3600);

        return $data;
    }
}
```

## CacheManager

`CacheManager` は WordPress Object Cache API をラップし、オブジェクト指向のインターフェースを提供します。

### メソッド一覧

| メソッド | 説明 |
|---------|------|
| `get(string $key, string $group = ''): mixed` | キャッシュ値を取得。見つからない場合は `false` |
| `set(string $key, mixed $data, string $group = '', int $expiration = 0): bool` | 値を保存（上書き） |
| `add(string $key, mixed $data, string $group = '', int $expiration = 0): bool` | 新規追加（既存キーがあれば失敗） |
| `replace(string $key, mixed $data, string $group = '', int $expiration = 0): bool` | 既存キーを置換（存在しなければ失敗） |
| `delete(string $key, string $group = ''): bool` | キャッシュ値を削除 |
| `flush(): bool` | 全キャッシュをクリア |
| `flushGroup(string $group): bool` | 指定グループのキャッシュをクリア（WP 6.1+） |
| `increment(string $key, int $offset = 1, string $group = ''): int\|false` | 数値をインクリメント |
| `decrement(string $key, int $offset = 1, string $group = ''): int\|false` | 数値をデクリメント |
| `supports(string $feature): bool` | 機能サポートの確認（例: `'flush_group'`） |

### 基本操作

```php
use WpPack\Component\Cache\CacheManager;

$cache = new CacheManager();

// データの保存
$cache->set('user_profile_123', $userData, 'my_app', 1800);

// データの取得
$data = $cache->get('user_profile_123', 'my_app');

// 新規追加（既存キーがあれば失敗）
$cache->add('counter', 0, 'my_app');

// 既存キーの置換（存在しなければ失敗）
$cache->replace('counter', 1, 'my_app');

// 削除
$cache->delete('user_profile_123', 'my_app');

// 全キャッシュのクリア
$cache->flush();
```

### キャッシュグループ

WordPress Object Cache のグループ機能を活用できます。

```php
$cache = new CacheManager();

// グループを指定して操作
$cache->set('key', $value, 'api_responses', 3600);
$data = $cache->get('key', 'api_responses');

// グループ単位でクリア（WP 6.1+）
if ($cache->supports('flush_group')) {
    $cache->flushGroup('api_responses');
}
```

### カウンター操作

```php
$cache = new CacheManager();

$cache->set('page_views', 0, 'stats');

// インクリメント / デクリメント
$cache->increment('page_views', 1, 'stats');
$cache->decrement('page_views', 1, 'stats');
```

## Object Cache ドロップイン

WordPress は `wp-content/object-cache.php` に配置されたドロップインファイルにより、Object Cache のバックエンドを Redis / Valkey / DynamoDB / APCu 等に切り替えることができます。

ドロップインはサードパーティプラグイン（Redis Object Cache、W3 Total Cache 等）や独自実装で提供されます。`CacheManager` はドロップインの有無にかかわらず同じインターフェースで動作します。

### 対応バックエンドの例

| バックエンド | 追加パッケージ |
|------------|--------------|
| Redis / Valkey | `ext-redis` または `predis/predis` |
| DynamoDB | `async-aws/dynamo-db` |
| APCu | `ext-apcu` |

ドロップインを配置しない場合、WordPress デフォルトのリクエスト内メモリキャッシュが使用されます。

### 動作確認

WP-CLI でキャッシュの状態を確認できます。

```bash
# キャッシュのテスト
wp cache set test_key test_value
wp cache get test_key
# => test_value
```

## WordPress イベントとの連携

コンテンツ変更時にキャッシュを自動的に無効化する例です。

```php
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Cache\CacheManager;

class CacheInvalidator
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    #[Action('save_post', priority: 10)]
    public function onPostUpdate(int $postId): void
    {
        $this->cache->delete("post_data_{$postId}", 'my_app');
    }
}
```

## 利用シーン

**Cache コンポーネントが適しているケース:**
- WordPress Object Cache API のモダンなラッパーが必要な場合
- Redis / Valkey / DynamoDB で永続キャッシュを導入したい場合
- リクエスト内キャッシュとして利用する場合
- キャッシュグループによる管理が必要な場合

**Transient コンポーネントの方が適しているケース:**
- 有効期限付きの一時データを保存したい場合
- ドロップインを導入しない環境（データベースに自動永続化される）
- `get_transient()` / `set_transient()` のラッパーが必要な場合

## 主要クラス

| クラス | 説明 |
|-------|------|
| `CacheManager` | WordPress Object Cache API のラッパー |

## 依存関係

### 必須
- なし（WordPress ネイティブの Object Cache API を使用）

### ドロップイン利用時
- Redis / Valkey: `ext-redis` または `predis/predis`
- DynamoDB: `async-aws/dynamo-db`
- APCu: `ext-apcu`
