# WordPress Options API 仕様

## 1. 概要

WordPress の Options API は、サイト全体の設定値をデータベースに永続化するための仕組みです。`wp_options` テーブルに Key-Value 形式でデータを格納し、キャッシュ層を通じて高速にアクセスできます。

Options API の状態は以下のグローバル変数とキャッシュで管理されます:

| グローバル変数 / キャッシュ | 型 | 説明 |
|---|---|---|
| `$wp_registered_settings` | `array` | `register_setting()` で登録された設定のメタ情報 |
| `alloptions` キャッシュ | `array` | `autoload = 'yes'` のオプションを一括キャッシュ |
| `notoptions` キャッシュ | `array` | 存在しないオプション名のネガティブキャッシュ |

WordPress 起動時、`wp_load_alloptions()` が `autoload = 'yes'` の全オプションを 1 回の SQL クエリで取得し、`alloptions` キャッシュに格納します。以降の `get_option()` 呼び出しはキャッシュから返されるため、データベースアクセスは発生しません。

## 2. データ構造

### `wp_options` テーブル

```sql
CREATE TABLE wp_options (
    option_id    BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    option_name  VARCHAR(191)        NOT NULL DEFAULT '',
    option_value LONGTEXT            NOT NULL DEFAULT '',
    autoload     VARCHAR(20)         NOT NULL DEFAULT 'yes',
    PRIMARY KEY (option_id),
    UNIQUE KEY option_name (option_name),
    KEY autoload (autoload)
);
```

| カラム | 説明 |
|---|---|
| `option_id` | 自動インクリメントの主キー |
| `option_name` | オプション名（最大 191 文字、ユニーク制約） |
| `option_value` | オプション値（LONGTEXT）。配列・オブジェクトは `maybe_serialize()` でシリアライズされて保存 |
| `autoload` | WordPress 起動時に自動読み込みするか。`'yes'`（デフォルト）/ `'no'` / `'on'` / `'off'` / `'auto'` / `'auto-on'` / `'auto-off'` |

### autoload の値

WordPress 6.6 以降、`autoload` カラムには以下の値が使われます:

| 値 | 説明 |
|---|---|
| `yes` / `on` | 常に自動読み込み |
| `no` / `off` | 自動読み込みしない |
| `auto` | WordPress が判断（新規作成時のデフォルト） |
| `auto-on` | `auto` から自動読み込み有効に昇格 |
| `auto-off` | `auto` から自動読み込み無効に降格 |

### キャッシュ戦略

`get_option()` は以下の順序で値を探索します:

```
get_option('my_option')
│
├── 1. pre_option_{$option} フィルターで短絡
│
├── 2. alloptions キャッシュを確認
│   └── 存在すれば即座に返す
│
├── 3. notoptions キャッシュを確認
│   └── 存在すれば default 値を返す
│
├── 4. options:{$option} キャッシュを確認
│   └── 存在すれば即座に返す
│
└── 5. データベースに SELECT クエリ
    ├── 存在 → options:{$option} キャッシュに格納して返す
    └── 不在 → notoptions キャッシュに追加して default 値を返す
```

## 3. API リファレンス

### 取得 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_option()` | `(string $option, mixed $default_value = false): mixed` | オプション値を取得 |
| `wp_load_alloptions()` | `(): array` | autoload 対象の全オプションを一括取得 |

`get_option()` の戻り値:
- オプションが存在する場合: その値（`maybe_unserialize()` でデシリアライズ済み）
- オプションが存在しない場合: `$default_value`（デフォルトは `false`）

> **注意**: スカラー値と `null` は `add_option()` で保存すると文字列に変換されます。配列・オブジェクトはシリアライズ / デシリアライズにより元の型を維持します。

### 追加 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `add_option()` | `(string $option, mixed $value = '', string $deprecated = '', bool\|null $autoload = null): bool` | 新しいオプションを追加 |

- 既にオプションが存在する場合は何もせず `false` を返す
- `$autoload` が `null` の場合、WordPress が自動判断（`auto`）
- `$deprecated` パラメータは歴史的理由で残っているが未使用

### 更新 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `update_option()` | `(string $option, mixed $value, bool\|null $autoload = null): bool` | オプション値を更新（存在しなければ追加） |

- 値が変更されていない場合は `false` を返す（データベース更新なし）
- オプションが存在しない場合は `add_option()` にフォールバック
- `$autoload` が `null` の場合、既存の autoload 設定を維持

### 削除 API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `delete_option()` | `(string $option): bool` | オプションを削除 |

- 保護されたオプション（`siteurl`, `home`, `blogname`, `blogdescription` 等）は `wp_protect_special_option()` により削除不可
- 削除後、`alloptions` または `options:{$option}` キャッシュを更新し、`notoptions` キャッシュに追加

### マルチサイト API

| 関数 | シグネチャ | 説明 |
|---|---|---|
| `get_site_option()` | `(string $option, mixed $default_value = false): mixed` | ネットワーク全体のオプションを取得 |
| `add_site_option()` | `(string $option, mixed $value): bool` | ネットワークオプションを追加 |
| `update_site_option()` | `(string $option, mixed $value): bool` | ネットワークオプションを更新 |
| `delete_site_option()` | `(string $option): bool` | ネットワークオプションを削除 |

マルチサイトでは `wp_sitemeta` テーブルに格納されます。シングルサイトでは `wp_options` にフォールバックします。

## 4. 実行フロー

### `update_option()` のフロー

```
update_option('my_option', $new_value, $autoload)
│
├── オプション名のバリデーション（trim、空文字チェック）
│
├── wp_protect_special_option() — 保護オプションの更新を防止
│
├── apply_filters('pre_update_option_{$option}', $new_value, $old_value, $option)
├── apply_filters('pre_update_option', $new_value, $option, $old_value)
│
├── $old_value = get_option($option)
│   └── オプションが存在しない場合 → add_option() にフォールバックして return
│
├── $old_value === $new_value の場合 → false を return（変更なし）
│
├── maybe_serialize($new_value)
│
├── do_action('update_option', $option, $old_value, $new_value)
│
├── UPDATE wp_options SET option_value = ... WHERE option_name = ...
│
├── キャッシュ更新
│   ├── autoload オプション → alloptions キャッシュを更新
│   └── 非 autoload オプション → options:{$option} キャッシュを更新
│
├── do_action("update_option_{$option}", $old_value, $new_value, $option)
├── do_action('updated_option', $option, $old_value, $new_value)
│
└── return true
```

### `add_option()` のフロー

```
add_option('my_option', $value, '', $autoload)
│
├── オプション名のバリデーション
├── wp_protect_special_option()
│
├── 既存オプションの存在チェック
│   └── 既に存在する場合 → false を return
│
├── maybe_serialize($value)
│
├── do_action('add_option', $option, $value)
│
├── INSERT INTO wp_options ... ON DUPLICATE KEY UPDATE
│
├── キャッシュ更新
│   ├── autoload → alloptions キャッシュに追加
│   └── 非 autoload → options:{$option} キャッシュに設定
│   └── notoptions キャッシュから削除
│
├── do_action("add_option_{$option}", $option, $value)
├── do_action('added_option', $option, $value)
│
└── return true
```

## 5. フック一覧

### Filter

| フック名 | パラメータ | 説明 |
|---|---|---|
| `pre_option_{$option}` | `(mixed $pre_option, string $option, mixed $default)` | オプション取得を短絡。`false` 以外を返すと DB クエリをスキップ |
| `default_option_{$option}` | `(mixed $default, string $option, bool $passed_default)` | デフォルト値をフィルタリング |
| `option_{$option}` | `(mixed $value, string $option)` | 取得したオプション値をフィルタリング |
| `pre_update_option_{$option}` | `(mixed $value, mixed $old_value, string $option)` | 更新前の値をフィルタリング |
| `pre_update_option` | `(mixed $value, string $option, mixed $old_value)` | 汎用の更新前フィルター |

### Action

| フック名 | パラメータ | 説明 |
|---|---|---|
| `add_option` | `(string $option, mixed $value)` | オプション追加前 |
| `add_option_{$option}` | `(string $option, mixed $value)` | 特定オプション追加後 |
| `added_option` | `(string $option, mixed $value)` | オプション追加後 |
| `update_option` | `(string $option, mixed $old_value, mixed $value)` | オプション更新前 |
| `update_option_{$option}` | `(mixed $old_value, mixed $value, string $option)` | 特定オプション更新後 |
| `updated_option` | `(string $option, mixed $old_value, mixed $value)` | オプション更新後 |
| `delete_option` | `(string $option)` | オプション削除前 |
| `delete_option_{$option}` | `(string $option)` | 特定オプション削除後 |
| `deleted_option` | `(string $option)` | オプション削除後 |

## 6. 保護されたオプション

`wp_protect_special_option()` により以下のオプションは `delete_option()` で削除できません:

- `alloptions`
- `notoptions`

また、以下のオプションは特殊な処理を受けます:

| オプション | 特殊処理 |
|---|---|
| `home` / `siteurl` | 末尾スラッシュを自動除去 |
| `category_base` / `tag_base` | 末尾スラッシュを自動除去 |
| `home` | 値が空の場合 `siteurl` にフォールバック |

## 7. シリアライズ

Options API は PHP の配列やオブジェクトを自動的にシリアライズ / デシリアライズします:

| 関数 | 説明 |
|---|---|
| `maybe_serialize()` | 配列・オブジェクト・二重シリアライズ済みデータをシリアライズ |
| `maybe_unserialize()` | シリアライズされた文字列をデシリアライズ |

```php
// 配列の保存と取得
update_option('my_array', ['key' => 'value', 'nested' => [1, 2, 3]]);
$arr = get_option('my_array'); // array が返る

// スカラー値は文字列に変換される
add_option('my_int', 42);
$val = get_option('my_int'); // '42'（文字列）
```
