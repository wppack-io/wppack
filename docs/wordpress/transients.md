# WordPress Transients API 仕様

## 1. 概要

Transients API は、有効期限付きのキャッシュデータをデータベースに一時保存するための仕組みです。Options API と同じ `wp_options` テーブルに格納されますが、有効期限（expiration）の概念を持つ点が異なります。

外部オブジェクトキャッシュ（Redis、Memcached 等）が有効な環境では、Transients API はデータベースではなくオブジェクトキャッシュに委譲します。これにより、プラグインやテーマはストレージ実装を意識せずにキャッシュ戦略を構築できます。

| 保存先 | 条件 | 説明 |
|---|---|---|
| `wp_options` テーブル | 外部オブジェクトキャッシュなし | `_transient_{$key}` と `_transient_timeout_{$key}` の 2 行で管理 |
| 外部オブジェクトキャッシュ | `wp_using_ext_object_cache()` が `true` | `wp_cache_set()` / `wp_cache_get()` に委譲。DB には保存されない |

## 2. データ構造

### `wp_options` テーブルでの保存形式

Transient は `wp_options` テーブルに以下の命名規則で保存されます:

| オプション名 | 型 | 説明 |
|---|---|---|
| `_transient_{$transient}` | `LONGTEXT` | Transient の値（シリアライズ済み） |
| `_transient_timeout_{$transient}` | `LONGTEXT` | 有効期限の Unix タイムスタンプ |

- Transient 名は最大 **172 文字**。`_transient_` プレフィックス（11 文字）と `_transient_timeout_` プレフィックス（19 文字）を付加して `option_name` カラム（191 文字制限）に収まる必要があるため
- 有効期限なし（`$expiration = 0`）の Transient は `_transient_timeout_` 行が作成されず、`autoload = 'yes'` として保存される
- 有効期限ありの Transient は `autoload = 'no'` として保存される

### マルチサイトでの保存形式

| オプション名 | 説明 |
|---|---|
| `_site_transient_{$transient}` | ネットワーク全体の Transient 値 |
| `_site_transient_timeout_{$transient}` | ネットワーク全体の有効期限 |

マルチサイトのサイト Transient は `wp_sitemeta` テーブルに格納されます。

## 3. API リファレンス

### サイト単位 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `set_transient()` | `(string $transient, mixed $value, int $expiration = 0): bool` | Transient を設定または更新 |
| `get_transient()` | `(string $transient): mixed` | Transient を取得 |
| `delete_transient()` | `(string $transient): bool` | Transient を削除 |

### ネットワーク単位 API（マルチサイト）

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `set_site_transient()` | `(string $transient, mixed $value, int $expiration = 0): bool` | ネットワーク Transient を設定 |
| `get_site_transient()` | `(string $transient): mixed` | ネットワーク Transient を取得 |
| `delete_site_transient()` | `(string $transient): bool` | ネットワーク Transient を削除 |

### `set_transient()` 詳細

```php
set_transient(string $transient, mixed $value, int $expiration = 0): bool
```

- `$transient`: Transient 名（最大 172 文字）
- `$value`: 保存する値。配列・オブジェクトは自動シリアライズ
- `$expiration`: 有効期限（秒）。`0` は無期限

定数を使った有効期限指定:

| 定数 | 値（秒） | 説明 |
|---|---|---|
| `MINUTE_IN_SECONDS` | 60 | 1 分 |
| `HOUR_IN_SECONDS` | 3600 | 1 時間 |
| `DAY_IN_SECONDS` | 86400 | 1 日 |
| `WEEK_IN_SECONDS` | 604800 | 1 週間 |
| `MONTH_IN_SECONDS` | 2592000 | 30 日 |
| `YEAR_IN_SECONDS` | 31536000 | 365 日 |

### `get_transient()` 詳細

```php
get_transient(string $transient): mixed
```

戻り値:
- Transient が存在し有効期限内: 保存された値
- Transient が存在しない / 有効期限切れ: `false`

> **注意**: `false` を値として保存した場合と、Transient が存在しない場合を区別するには、厳密比較演算子（`===`）を使用してください。

### `delete_transient()` 詳細

```php
delete_transient(string $transient): bool
```

- 成功時: `true`
- 失敗時（存在しない等）: `false`

## 4. 実行フロー

### `get_transient()` のフロー

```
get_transient('my_cache')
│
├── apply_filters('pre_transient_{$transient}', false, $transient)
│   └── false 以外が返された場合 → その値を即座に return
│
├── wp_using_ext_object_cache() が true の場合
│   ├── wp_cache_get($transient, 'transient')
│   └── 値を return（キャッシュミスなら false）
│
├── wp_options テーブルから取得
│   ├── _transient_timeout_{$transient} を確認
│   │   └── 有効期限切れの場合
│   │       ├── delete_transient($transient) — 期限切れを削除
│   │       └── false を return
│   │
│   └── get_option('_transient_' . $transient) で値を取得
│
├── apply_filters('transient_{$transient}', $value, $transient)
│
└── return $value
```

### `set_transient()` のフロー

```
set_transient('my_cache', $value, 3600)
│
├── apply_filters('pre_set_transient_{$transient}', $value, $expiration, $transient)
├── apply_filters('expiration_of_transient_{$transient}', $expiration, $value, $transient)
│
├── wp_using_ext_object_cache() が true の場合
│   ├── wp_cache_set($transient, $value, 'transient', $expiration)
│   └── return $result
│
├── wp_options テーブルに保存
│   ├── 既存 Transient の存在チェック（get_option で確認）
│   │
│   ├── 既存あり（更新）
│   │   ├── $expiration > 0 の場合
│   │   │   └── update_option('_transient_timeout_' . $transient, time() + $expiration)
│   │   └── update_option('_transient_' . $transient, $value)
│   │
│   └── 既存なし（新規追加）
│       ├── $expiration > 0 の場合
│       │   ├── add_option('_transient_timeout_' . $transient, time() + $expiration, '', 'no')
│       │   └── add_option('_transient_' . $transient, $value, '', 'no')  ← autoload = 'no'
│       └── $expiration == 0 の場合
│           └── add_option('_transient_' . $transient, $value)  ← autoload = デフォルト（'yes'）
│
├── do_action('set_transient_{$transient}', $value, $expiration, $transient)
├── do_action('set_transient', $transient, $value, $expiration)
│
└── return $result
```

### 有効期限切れの削除タイミング

WordPress は期限切れ Transient を自動的に定期削除する仕組みを持ちます:

1. **Lazy deletion**: `get_transient()` 呼び出し時に期限切れを検出すると即座に削除
2. **Cron による一括削除**: `delete_expired_transients` イベント（1 日 2 回）で期限切れ Transient を一括削除

```php
// delete_expired_transients() は以下の SQL で一括削除
DELETE a, b FROM wp_options a, wp_options b
WHERE a.option_name LIKE '_transient_timeout_%'
AND a.option_value < UNIX_TIMESTAMP()
AND b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 20))
```

## 5. フック一覧

### Filter

| フック名 | パラメータ | 説明 |
|---|---|---|
| `pre_transient_{$transient}` | `(mixed $pre_transient, string $transient)` | Transient 取得を短絡。`false` 以外を返すと DB/キャッシュクエリをスキップ |
| `transient_{$transient}` | `(mixed $value, string $transient)` | 取得した Transient 値をフィルタリング |
| `pre_set_transient_{$transient}` | `(mixed $value, int $expiration, string $transient)` | 保存前の値をフィルタリング |
| `expiration_of_transient_{$transient}` | `(int $expiration, mixed $value, string $transient)` | 有効期限をフィルタリング |
| `pre_site_transient_{$transient}` | `(mixed $pre_site_transient, string $transient)` | サイト Transient 取得を短絡 |
| `site_transient_{$transient}` | `(mixed $value, string $transient)` | サイト Transient 値をフィルタリング |
| `pre_set_site_transient_{$transient}` | `(mixed $value, int $expiration, string $transient)` | サイト Transient 保存前フィルター |
| `expiration_of_site_transient_{$transient}` | `(int $expiration, mixed $value, string $transient)` | サイト Transient 有効期限フィルター |

### Action

| フック名 | パラメータ | 説明 |
|---|---|---|
| `set_transient_{$transient}` | `(mixed $value, int $expiration, string $transient)` | Transient 設定後 |
| `set_transient` | `(string $transient, mixed $value, int $expiration)` | 任意の Transient 設定後 |
| `set_site_transient_{$transient}` | `(mixed $value, int $expiration, string $transient)` | サイト Transient 設定後 |
| `set_site_transient` | `(string $transient, mixed $value, int $expiration)` | 任意のサイト Transient 設定後 |
| `delete_transient_{$transient}` | `(string $transient)` | Transient 削除後 |
| `deleted_transient` | `(string $transient)` | Transient 削除後（汎用） |
| `delete_site_transient_{$transient}` | `(string $transient)` | サイト Transient 削除後 |
| `deleted_site_transient` | `(string $transient)` | サイト Transient 削除後（汎用） |

## 6. 外部オブジェクトキャッシュとの関係

外部オブジェクトキャッシュが有効な場合、Transients API の動作が大きく変わります:

| 操作 | DB 保存（デフォルト） | 外部オブジェクトキャッシュ |
|---|---|---|
| `set_transient()` | `wp_options` に INSERT/UPDATE | `wp_cache_set()` に委譲 |
| `get_transient()` | `wp_options` から SELECT | `wp_cache_get()` に委譲 |
| `delete_transient()` | `wp_options` から DELETE | `wp_cache_delete()` に委譲 |
| 有効期限管理 | `_transient_timeout_` 行で管理 | キャッシュバックエンドの TTL に委譲 |
| キャッシュグループ | N/A | `'transient'` グループ |

外部キャッシュ使用時は `wp_options` テーブルへの書き込みが一切発生しないため、データベース負荷が大幅に軽減されます。`wp_using_ext_object_cache()` が判定に使用されます。
