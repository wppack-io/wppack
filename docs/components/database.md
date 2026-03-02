# Database コンポーネント

Database コンポーネントは、WordPress の `$wpdb` をラップした流暢なクエリビルダーと、`dbDelta()` によるカスタムテーブルのスキーマ管理を提供します。

## Database と Query の違い

WpPack では、データベース操作を2つのコンポーネントに分割しています：

| | Database コンポーネント | Query コンポーネント |
|---|---|---|
| 対象 | カスタムテーブル | WordPress ネイティブデータ |
| 内部実装 | `$wpdb` fluent wrapper | `WP_Query` / `WP_User_Query` / `WP_Term_Query` fluent wrapper |
| 主な用途 | カスタムテーブルの CRUD、スキーマ管理 | 投稿・ユーザー・タームの検索・取得 |
| スキーマ管理 | `dbDelta()` によるテーブル作成・更新 | 不要（WordPress コアが管理） |

**Database コンポーネント** は、`$wpdb` を直接操作する代わりに、型安全で流暢なクエリビルダーを提供します。カスタムテーブルの作成には WordPress の `dbDelta()` 関数を利用し、スキーマの宣言的な管理を可能にします。

**Query コンポーネント** は、`WP_Query`、`WP_User_Query`、`WP_Term_Query` などの WordPress ネイティブクエリクラスを流暢な API でラップします。投稿、ユーザー、タクソノミーなど WordPress が管理するデータへのアクセスに使用してください。

## インストール

```bash
composer require wppack/database
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - SQL インジェクションのリスクがある直接クエリ
global $wpdb;
$results = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}custom_table WHERE status = 'active'"
);

// prepare() を使ってもコードが読みにくい
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}custom_table WHERE status = %s AND priority >= %d",
    'active',
    5
));
```

### After（WpPack）

```php
// WpPack - 型安全で流暢なクエリビルダー
$results = $this->db->table('custom_table')
    ->where('status', 'active')
    ->where('priority', '>=', 5)
    ->orderBy('created_at', 'desc')
    ->get();
```

## 主要機能

### 流暢なクエリビルダー

チェーン可能なメソッドでクエリを組み立てます。内部的に `$wpdb::prepare()` を使用するため、SQL インジェクションを自動的に防止します。

```php
use WpPack\Component\Database\DatabaseManager;

class AnalyticsRepository
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    public function findActiveRecords(int $minPriority = 0): Collection
    {
        return $this->db->table('analytics_records')
            ->select(['id', 'url', 'view_count', 'created_at'])
            ->where('status', 'active')
            ->where('priority', '>=', $minPriority)
            ->whereNotNull('url')
            ->orderBy('view_count', 'desc')
            ->limit(50)
            ->get();
    }

    public function getStats(): array
    {
        return [
            'total' => $this->db->table('analytics_records')->count(),
            'active' => $this->db->table('analytics_records')
                ->where('status', 'active')
                ->count(),
            'total_views' => $this->db->table('analytics_records')
                ->sum('view_count'),
        ];
    }
}
```

### #[Table] によるスキーマ定義

`#[Table]` アトリビュートでカスタムテーブルのスキーマを宣言的に定義します。テーブルの作成・更新には WordPress の `dbDelta()` 関数が使用されます。

```php
use WpPack\Component\Database\Attribute\Table;

#[Table('user_preferences')]
class UserPreferencesTable
{
    public static function schema(): string
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'user_preferences';
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            preference_key varchar(100) NOT NULL,
            preference_value text NOT NULL,
            is_public tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_pref (user_id, preference_key),
            KEY preference_key (preference_key)
        ) {$charset_collate};";
    }
}
```

プラグインの有効化時に `dbDelta()` を通じてテーブルが自動的に作成・更新されます。

### カスタムテーブルの CRUD 操作

```php
class UserPreferenceService
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    public function setPreference(int $userId, string $key, string $value): void
    {
        $this->db->table('user_preferences')->updateOrInsert(
            ['user_id' => $userId, 'preference_key' => $key],
            ['preference_value' => $value, 'updated_at' => now()],
        );
    }

    public function getPreference(int $userId, string $key): ?string
    {
        $pref = $this->db->table('user_preferences')
            ->where('user_id', $userId)
            ->where('preference_key', $key)
            ->first();

        return $pref?->preference_value;
    }

    public function getAllPreferences(int $userId): array
    {
        return $this->db->table('user_preferences')
            ->where('user_id', $userId)
            ->pluck('preference_value', 'preference_key')
            ->toArray();
    }

    public function deletePreference(int $userId, string $key): bool
    {
        return $this->db->table('user_preferences')
            ->where('user_id', $userId)
            ->where('preference_key', $key)
            ->delete() > 0;
    }
}
```

### JOIN とアグリゲーション

```php
public function getPreferenceStats(): array
{
    return $this->db->table('user_preferences')
        ->select([
            'preference_key',
            $this->db->raw('COUNT(*) as usage_count'),
            $this->db->raw('COUNT(DISTINCT user_id) as unique_users'),
        ])
        ->groupBy('preference_key')
        ->orderBy('usage_count', 'desc')
        ->get()
        ->toArray();
}

public function getUsersWithPreferenceCount(): Collection
{
    return $this->db->table('users')
        ->select([
            'users.ID',
            'users.display_name',
            $this->db->raw('COUNT(prefs.id) as preference_count'),
        ])
        ->leftJoin(
            'user_preferences as prefs',
            'users.ID',
            '=',
            'prefs.user_id',
        )
        ->groupBy('users.ID', 'users.display_name')
        ->get();
}
```

### WordPress テーブルとの結合

WordPress コアのテーブル（`posts`、`postmeta` など）に対してもクエリビルダーを使用できます。

```php
public function getPostsWithCustomData(): Collection
{
    return $this->db->table('posts')
        ->join('user_preferences', function ($join) {
            $join->on('posts.post_author', '=', 'user_preferences.user_id')
                 ->where('user_preferences.preference_key', '=', 'author_bio');
        })
        ->select(['posts.*', 'user_preferences.preference_value as author_bio'])
        ->where('posts.post_status', 'publish')
        ->where('posts.post_type', 'post')
        ->get();
}
```

## リポジトリパターン

```php
class UserPreferenceRepository
{
    public function __construct(
        private DatabaseManager $db,
    ) {}

    public function findByUser(int $userId): Collection
    {
        return $this->db->table('user_preferences')
            ->where('user_id', $userId)
            ->get();
    }

    public function findPublic(): Collection
    {
        return $this->db->table('user_preferences')
            ->where('is_public', true)
            ->get();
    }

    public function create(array $data): int
    {
        return $this->db->table('user_preferences')->insertGetId([
            'user_id' => $data['user_id'],
            'preference_key' => $data['key'],
            'preference_value' => $data['value'],
            'is_public' => $data['public'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateOrCreate(int $userId, string $key, string $value): void
    {
        $this->db->table('user_preferences')->updateOrInsert(
            ['user_id' => $userId, 'preference_key' => $key],
            ['preference_value' => $value, 'updated_at' => now()],
        );
    }

    public function delete(int $userId, string $key): bool
    {
        return $this->db->table('user_preferences')
            ->where('user_id', $userId)
            ->where('preference_key', $key)
            ->delete() > 0;
    }
}
```

## Named Hooks

Database コンポーネントは、WordPress のデータベース関連フックを Named Hook アトリビュートとして提供します。

### クエリフック

#### #[QueryFilter]

**WordPress Hook:** `query`
SQL クエリの実行前にクエリを変更します。

```php
use WpPack\Component\Database\Attribute\QueryFilter;

class DatabaseQueryManager
{
    #[QueryFilter(priority: 10)]
    public function optimizeQueries(string $query): string
    {
        if (stripos($query, 'SELECT') === 0) {
            $query = $this->addQueryComment($query);
        }

        return $query;
    }
}
```

#### #[DbprepareFilter]

**WordPress Hook:** `query`
`prepare()` 後、実行前のクエリをフィルタリングします。

```php
use WpPack\Component\Database\Attribute\DbprepareFilter;

class DatabasePrepareManager
{
    #[DbprepareFilter(priority: 10)]
    public function filterPreparedQueries(string $query): string
    {
        if ($this->shouldTagQuery($query)) {
            $tag = $this->generateQueryTag();
            $query = "/* Tag: {$tag} */ " . $query;
        }

        return $query;
    }
}
```

### スキーマフック

#### #[WpUpgradeAction]

**WordPress Hook:** `wp_upgrade`
WordPress アップグレード時にデータベーススキーマの更新を実行します。

```php
use WpPack\Component\Database\Attribute\WpUpgradeAction;

class DatabaseSchemaManager
{
    #[WpUpgradeAction(priority: 10)]
    public function upgradeDatabaseSchema(string $wp_db_version, string $wp_current_db_version): void
    {
        $this->runDbDeltaUpdates();
        update_option('wppack_db_version', WPPACK_DB_VERSION);
    }
}
```

#### #[DbDeltaQueriesFilter]

**WordPress Hook:** `dbdelta_queries`
`dbDelta()` 実行前にクエリを変更します。

```php
use WpPack\Component\Database\Attribute\DbDeltaQueriesFilter;

class DatabaseTableManager
{
    #[DbDeltaQueriesFilter(priority: 10)]
    public function modifyDbDeltaQueries(array $queries): array
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $queries[] = "CREATE TABLE {$wpdb->prefix}wppack_statistics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            view_count bigint(20) DEFAULT 0,
            unique_visitors bigint(20) DEFAULT 0,
            last_viewed datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY last_viewed (last_viewed)
        ) {$charset_collate};";

        return $queries;
    }
}
```

### Hook アトリビュート一覧

```php
// クエリフック（wpdb）
#[QueryFilter(priority: 10)]                  // SQL クエリのフィルタリング
#[DbprepareFilter(priority: 10)]              // prepare 済みクエリのフィルタリング

// スキーマフック
#[WpUpgradeAction(priority: 10)]              // データベースアップグレード
#[DbDeltaQueriesFilter(priority: 10)]         // dbDelta クエリ
#[DbDeltaCreateQueriesFilter(priority: 10)]   // テーブル作成クエリ
#[DbDeltaInsertQueriesFilter(priority: 10)]   // INSERT クエリ

```

## クイックリファレンス

### 主要メソッド

```php
// クエリビルダー
$this->db->table('custom_table')->where('active', true)->get()  // 条件付き SELECT
$this->db->table('custom_table')->find(123)                     // ID で取得
$this->db->table('custom_table')->insert($data)                 // INSERT
$this->db->table('custom_table')->where(...)->update($data)     // UPDATE
$this->db->table('custom_table')->where(...)->delete()          // DELETE

// アグリゲーション
$this->db->table('custom_table')->count()                       // 件数
$this->db->table('custom_table')->sum('amount')                 // 合計
$this->db->table('custom_table')->avg('price')                  // 平均
$this->db->table('custom_table')->pluck('name', 'id')           // カラム抽出
$this->db->table('custom_table')->paginate(10)                  // ページネーション
```

## このコンポーネントの使い分け

**Database コンポーネントを使う場合：**
- カスタムテーブルの CRUD 操作
- カスタムテーブルのスキーマ管理（`dbDelta()`）
- JOIN やアグリゲーションを含む複雑なクエリ
- レポート・分析用の集計クエリ

**Query コンポーネントを使う場合：**
- 投稿の検索・取得（`WP_Query` ラッパー）
- ユーザーの検索・取得（`WP_User_Query` ラッパー）
- タームの検索・取得（`WP_Term_Query` ラッパー）
- WordPress ネイティブデータへのアクセス全般

## セキュリティ

- **自動パラメータバインディング** - `$wpdb::prepare()` を内部利用し、SQL インジェクションを防止
- **型安全なクエリ** - 入力の検証とサニタイズ
- **クエリログ** - セキュリティ監査用のログ記録

## 依存コンポーネント

### 必須
- **DependencyInjection** - サービスコンテナとデータベースサービスの管理
- **Config** - データベース設定の管理

### 推奨
- **Hook** - WordPress データベースフックとの統合
- **Cache** - クエリ結果のキャッシュ
