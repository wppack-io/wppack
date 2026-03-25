# WordPress ドロップイン仕様

## 概要

ドロップインは WordPress の特定のサブシステムを丸ごと差し替える仕組みです。`wp-content/` ディレクトリに所定のファイル名で配置すると、WordPress コアの対応するデフォルト実装の代わりにそのファイルが読み込まれます。

プラグインとは異なり、管理画面から有効化/無効化する UI はなく、ファイルの存在自体がアクティベーション条件です。

## ドロップイン一覧

### 起動初期（`wp-settings.php` 序盤）

WordPress の起動処理の非常に早い段階で読み込まれるドロップイン。プラグインやテーマよりも先にロードされます。

| ファイル | 読み込みタイミング | 用途 | 条件 |
|---------|-------------------|------|------|
| `db.php` | 最も早い。`$wpdb` 生成直前（`wp-includes/load.php`） | カスタム DB クラス（`$wpdb` の差し替え） | ファイルの存在のみ |
| `object-cache.php` | `db.php` の直後（`wp-includes/load.php`） | オブジェクトキャッシュバックエンドの差し替え | ファイルの存在のみ |
| `advanced-cache.php` | `wp-settings.php` 序盤 | ページキャッシュ等の早期フック | `WP_CACHE === true` が必要 |

### マルチサイト起動時

マルチサイト環境でのみ意味を持つドロップイン。

| ファイル | 読み込みタイミング | 用途 | 条件 |
|---------|-------------------|------|------|
| `sunrise.php` | `ms-settings.php`（マルチサイト初期化時） | ドメインマッピング、サイト解決のカスタマイズ | `SUNRISE` 定数の定義が必要 |
| `blog-deleted.php` | サイト状態チェック時 | 削除済みサイトのカスタム表示 | 該当サイトが `deleted` 状態 |
| `blog-inactive.php` | サイト状態チェック時 | 無効サイトのカスタム表示 | 該当サイトが `inactive` 状態 |
| `blog-suspended.php` | サイト状態チェック時 | 停止サイトのカスタム表示 | 該当サイトが `suspended` 状態 |

### エラー・メンテナンス

通常の起動フローではなく、特定の状況下で読み込まれるドロップイン。

| ファイル | 読み込みタイミング | 用途 | 条件 |
|---------|-------------------|------|------|
| `maintenance.php` | `.maintenance` ファイル検出時 | メンテナンス画面のカスタマイズ | `.maintenance` ファイルの存在 |
| `php-error.php` | PHP エラー表示時 | エラーテンプレートの差し替え | — |
| `fatal-error-handler.php` | 致命的エラー発生時（WP 5.2+） | `WP_Fatal_Error_Handler` の差し替え | — |

## 読み込み順序の詳細

WordPress の起動処理（`wp-settings.php`）における、ドロップインとプラグインの読み込み順序:

```
1. wp-config.php
   └─ 定数の定義（ABSPATH, WP_CONTENT_DIR, WP_CACHE, SUNRISE 等）

2. wp-settings.php 開始
   │
   ├─ wp-includes/load.php
   │   ├─ db.php                    ← (1) 最初のドロップイン
   │   └─ object-cache.php          ← (2) wp_cache_init() 呼び出し
   │
   ├─ advanced-cache.php            ← (3) WP_CACHE === true 時のみ
   │
   ├─ ms-settings.php（マルチサイト）
   │   └─ sunrise.php               ← (4) SUNRISE 定義時のみ
   │
   ├─ mu-plugins（Must-Use プラグイン）
   │
   ├─ plugins（通常のプラグイン）
   │
   ├─ pluggable.php
   │
   ├─ do_action('init')
   │
   └─ テーマの読み込み
```

## 各ドロップインの仕様

### db.php

WordPress のデータベースクラス `$wpdb` を差し替えるドロップイン。`wp-includes/load.php` の `require_wp_db()` 内で読み込まれます。

**読み込みロジック**:
```php
// wp-includes/load.php — require_wp_db()
if ( file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
    require_once WP_CONTENT_DIR . '/db.php';
}
```

**要件**:
- `$wpdb` グローバル変数を設定すること
- `wpdb` クラスを拡張するか、互換性のある API を提供すること
- WordPress コア・プラグインが `$wpdb->query()`, `$wpdb->prepare()`, `$wpdb->get_results()` 等を呼び出すため、全メソッドの互換性が必要

**ユースケース**:
- クエリロギング・プロファイリング
- リードレプリカへの自動ルーティング
- 接続プーリング
- カスタム DB ドライバの使用

**注意点**:
- 最も早いタイミングで読み込まれるため、Composer autoloader を含むほとんどの外部依存が使えない可能性がある
- `object-cache.php` よりも前に読み込まれるため、キャッシュが利用不可

### object-cache.php

WordPress のオブジェクトキャッシュバックエンドを差し替えるドロップイン。デフォルトの `WP_Object_Cache`（リクエスト内メモリのみ）を Redis 等の永続キャッシュに置き換えます。

詳細: [object-cache-dropin.md](./object-cache-dropin.md)

### advanced-cache.php

WordPress の起動処理の早い段階でカスタムコードを実行するためのドロップイン。主にページキャッシュプラグインがフルページキャッシュのヒット判定・配信に使用します。

**読み込み条件**: `wp-config.php` で `WP_CACHE` 定数を `true` に設定する必要があります。

```php
// wp-config.php
define('WP_CACHE', true);
```

**読み込みロジック**:
```php
// wp-settings.php
if ( WP_CACHE && apply_filters( 'enable_loading_advanced_cache_dropin', true ) ) {
    WP_DEBUG ? include( WP_CONTENT_DIR . '/advanced-cache.php' )
             : @include( WP_CONTENT_DIR . '/advanced-cache.php' );
}
```

**ユースケース**:
- フルページキャッシュ（Redis / Memcached / ファイルベース）
- リクエストレベルの早期フック（プラグインロード前に介入）
- CDN 統合の前処理

**注意点**:
- `object-cache.php` はロード済みのため、オブジェクトキャッシュは利用可能
- プラグインはまだ読み込まれていないため、プラグイン提供のクラスに依存できない
- ページキャッシュのヒット時に `exit` してレスポンスを返すパターンが一般的

### sunrise.php

マルチサイト環境の初期化時に実行されるドロップイン。WordPress がリクエストされたドメイン/パスからサイトを特定する前後の処理をカスタマイズできます。

**読み込み条件**: `wp-config.php` で `SUNRISE` 定数を定義する必要があります。

```php
// wp-config.php
define('SUNRISE', true);
```

**読み込みロジック**:
```php
// wp-includes/ms-settings.php
if ( defined( 'SUNRISE' ) ) {
    include_once WP_CONTENT_DIR . '/sunrise.php';
}
```

**ユースケース**:
- カスタムドメインマッピング（`example.com` → サイト ID 3）
- マルチサイトのサイト解決ロジックの変更
- ネットワーク/サイトの早期リダイレクト

**注意点**:
- マルチサイト有効時（`is_multisite() === true`）のみ意味がある
- `$current_blog`, `$current_site` 等のグローバル変数を設定可能
- `object-cache.php` と `advanced-cache.php` はロード済み

### blog-deleted.php / blog-inactive.php / blog-suspended.php

マルチサイト環境でサイトの状態が正常でない場合に表示されるカスタムページ。

**デフォルト動作**: WordPress コアがデフォルトのエラーメッセージを表示して `die()` します。ドロップインを配置するとデフォルトメッセージの代わりにカスタムテンプレートが使われます。

**注意点**:
- これらのファイルは読み込み後に `die()` / `exit` することが期待される
- デザインのカスタマイズ以外の用途は限定的

### maintenance.php

WordPress がアップデート中などで `.maintenance` ファイルが存在する場合に表示されるメンテナンスページ。

**読み込み条件**: ABSPATH に `.maintenance` ファイルが存在し、かつその中の `$upgrading` タイムスタンプが 10 分以内であること。

**デフォルト動作**: 「現在メンテナンス中です」というデフォルトメッセージを表示します。ドロップインでカスタマイズ可能です。

### fatal-error-handler.php

WordPress 5.2+ で追加された致命的エラーハンドラの差し替え。`WP_Fatal_Error_Handler` クラスをカスタム実装に置き換えます。

**要件**: `WP_Fatal_Error_Handler` 互換のクラスを返すか、同名のクラスを定義する。

**ユースケース**:
- カスタムエラーページの表示
- エラー通知の外部サービスへの送信（Sentry 等）
- エラーログのフォーマット変更

## サーバーレス環境での注意事項

Lambda / Cloud Functions 等の読み取り専用ファイルシステムでは、ドロップインに関して以下の制約があります:

### ファイルの配置

ドロップインファイルはデプロイアーティファクトに含める必要があります。プラグインの有効化/無効化による自動配置/削除は動作しません。

```bash
# ビルドステップでドロップインを配置
cp vendor/wppack/cache/drop-in/object-cache.php wp-content/object-cache.php
```

### ドロップインの無効化

ファイルを削除できないため、ドロップイン側にキルスイッチを実装する必要があります。WpPack の `object-cache.php` は `WPPACK_CACHE_ENABLED` 定数でこれに対応しています:

```php
// wp-config.php — ドロップインを無効化
define('WPPACK_CACHE_ENABLED', false);
```

`WPPACK_CACHE_ENABLED` が `false` の場合、ドロップインは外部キャッシュへの接続を行わず、インメモリフォールバック（`ObjectCache(null)`）で動作します。

## 管理画面での確認

WordPress 管理画面からドロップインの状態を確認できます:

- **プラグイン画面**: 「ドロップイン」タブ（WordPress がドロップインとして認識したファイル一覧）
- **サイトヘルス → 情報 → WordPress 定数**: `WP_CACHE` 等の関連定数
- **WP-CLI**: `wp dropins list`（CLI で一覧表示）

## WpPack での対応状況

| ドロップイン | WpPack パッケージ | 状態 |
|-------------|------------------|------|
| `object-cache.php` | `wppack/cache` | 実装済み |
| `db.php` | `wppack/database`（候補） | 未実装 |
| `advanced-cache.php` | — | 未計画 |
| `sunrise.php` | `wppack/site`（候補） | 未計画 |
| その他 | — | 対象外 |
