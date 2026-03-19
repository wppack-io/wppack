# WordPress プラグインライフサイクル仕様

## 1. 概要

WordPress プラグインには明確なライフサイクルがあります: **インストール → 有効化 → 実行 → 無効化 → アンインストール**。各フェーズで特定のフックが発火し、プラグインはデータベーステーブルの作成、オプションの初期化、クリーンアップ処理などを適切なタイミングで実行できます。

プラグインの状態は以下のオプションで管理されます:

| オプション名 | 型 | 説明 |
|---|---|---|
| `active_plugins` | `array` | 有効なプラグインのファイルパスの配列（サイト単位） |
| `active_sitewide_plugins` | `array` | ネットワーク全体で有効なプラグインの配列（マルチサイト） |
| `recently_activated` | `array` | 最近無効化されたプラグインの配列（UI 表示用） |
| `uninstall_plugins` | `array` | アンインストールコールバックの登録情報 |

プラグインのファイルパスは `plugin_basename()` で正規化され、`{plugin-dir}/{plugin-file}.php`（サブディレクトリプラグイン）または `{plugin-file}.php`（単一ファイルプラグイン）の形式です。

## 2. ライフサイクルフェーズ

### 全体フロー

```
[インストール]
└── プラグインファイルが wp-content/plugins/ に配置される
    （WordPress は特別な処理を行わない）

[有効化]
├── activate_plugin() が呼ばれる
├── プラグインファイルを読み込み（サンドボックス）
├── activate_{$plugin} アクションが発火
├── active_plugins オプションを更新
└── activated_plugin アクションが発火

[実行]（WordPress リクエストごと）
├── wp-settings.php で active_plugins を読み込み
├── 各プラグインファイルを include
└── プラグインのフック登録・初期化処理が実行される

[無効化]
├── deactivate_{$plugin} アクションが発火
├── active_plugins オプションから削除
└── deactivated_plugin アクションが発火

[アンインストール]
├── uninstall_{$plugin} アクションが発火
│   または uninstall.php が実行される
└── プラグインデータの完全削除
```

## 3. 有効化

### `activate_plugin()` 関数

```php
activate_plugin(
    string $plugin,
    string $redirect = '',
    bool   $network_wide = false,
    bool   $silent = false
): null|WP_Error
```

| パラメータ | 説明 |
|---|---|
| `$plugin` | プラグインのベースネーム（例: `my-plugin/my-plugin.php`） |
| `$redirect` | 有効化後のリダイレクト先 URL |
| `$network_wide` | マルチサイトで全サイトに対して有効化するか |
| `$silent` | `true` の場合、有効化フックを発火しない |

戻り値:
- `null`: 有効化成功
- `WP_Error`: バリデーション失敗またはエラー発生

### 有効化フロー詳細

```
activate_plugin('my-plugin/my-plugin.php')
│
├── 1. バリデーション
│   ├── plugin_basename() でパスを正規化
│   ├── validate_plugin() でファイル存在確認
│   └── プラグイン要件チェック（PHP バージョン、WP バージョン）
│       └── 要件未満 → WP_Error を return
│
├── 2. 既に有効化されているかチェック
│   └── 有効 → null を return（何もしない）
│
├── 3. サンドボックステスト
│   ├── ob_start() で出力バッファリング開始
│   ├── wp_register_plugin_realpath($plugin)
│   ├── $_wp_plugin_file = $plugin
│   ├── include_once(WP_PLUGIN_DIR . '/' . $plugin)
│   │   └── プラグインファイルの読み込み（構文エラー・致命的エラーの検出）
│   └── ob_get_clean() で出力を取得
│       └── 出力があった場合 → WP_Error を return
│
├── 4. フック発火（$silent = false の場合のみ）
│   ├── do_action('activate_plugin', $plugin, $network_wide)
│   ├── do_action('activate_' . $plugin, $network_wide)
│   └── ※ register_activation_hook() のコールバックはここで実行される
│
├── 5. active_plugins オプションの更新
│   ├── $network_wide = true
│   │   └── update_site_option('active_sitewide_plugins', ...)
│   └── $network_wide = false
│       └── update_option('active_plugins', ...)
│
├── 6. recently_activated から削除
│
├── 7. do_action('activated_plugin', $plugin, $network_wide)
│
└── return null
```

### `register_activation_hook()`

```php
function register_activation_hook(string $file, callable $callback): void {
    $file = plugin_basename($file);
    add_action('activate_' . $file, $callback);
}
```

- `$file`: プラグインのメインファイルパス（通常 `__FILE__`）
- `$callback`: 有効化時に実行されるコールバック。引数として `$network_wide`（bool）を受け取る

フック名の生成ルール:

| プラグイン配置 | フック名 |
|---|---|
| `wp-content/plugins/sample.php` | `activate_sample.php` |
| `wp-content/plugins/my-plugin/my-plugin.php` | `activate_my-plugin/my-plugin.php` |

> [!WARNING]
> `register_activation_hook()` はプラグインのメインファイルのトップレベルで呼ぶ必要があります。`plugins_loaded` や `init` 等のフック内で登録しても、有効化時にはそれらのフックは発火しないため動作しません。

### 有効化時の典型的な処理

```php
register_activation_hook(__FILE__, function (bool $network_wide): void {
    // データベーステーブルの作成
    global $wpdb;
    $table = $wpdb->prefix . 'my_table';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        PRIMARY KEY (id)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    // デフォルトオプションの設定
    add_option('my_plugin_version', '1.0.0');

    // Rewrite ルールのフラッシュ
    flush_rewrite_rules();
});
```

## 4. 実行（ロード）

### プラグインロードの順序

WordPress のブートストラップ（`wp-settings.php`）でプラグインがロードされます:

```
wp-settings.php
│
├── SHORTINIT が true の場合 → プラグインをロードしない
│
├── 1. Must-Use プラグイン（mu-plugins）のロード
│   ├── wp_get_mu_plugins() でファイル一覧取得
│   ├── 各ファイルを include_once
│   └── do_action('muplugins_loaded')
│
├── 2. ネットワークプラグインのロード（マルチサイトのみ）
│   ├── active_sitewide_plugins オプションから取得
│   └── 各プラグインを include_once
│
├── 3. 通常プラグインのロード
│   ├── active_plugins オプションから取得
│   ├── wp_is_recovery_mode_active() で回復モードを確認
│   │   └── 回復モード: 問題のあるプラグインをスキップ
│   ├── 各プラグインを include_once
│   └── $GLOBALS['wp_plugin_paths'] にパスを登録
│
├── 4. do_action('plugins_loaded')
│
├── 5. ... テーマ・その他の初期化 ...
│
└── 6. do_action('init')
```

### プラグインロード順の制御

`active_plugins` オプションの配列順序がロード順序です。プラグインの依存関係を保証する仕組みは標準では提供されていません。`pre_update_option_active_plugins` フィルターで順序を変更可能ですが、推奨される方法ではありません。

## 5. 無効化

### `deactivate_plugins()` 関数

```php
deactivate_plugins(
    string|string[] $plugins,
    bool            $silent = false,
    bool|null       $network_wide = null
): void
```

| パラメータ | 説明 |
|---|---|
| `$plugins` | 無効化するプラグイン（単一またはリスト） |
| `$silent` | `true` の場合、無効化フックを発火しない。プラグイン更新時に使用 |
| `$network_wide` | マルチサイトでネットワーク全体から無効化するか |

### 無効化フロー詳細

```
deactivate_plugins('my-plugin/my-plugin.php')
│
├── 各プラグインに対して:
│   │
│   ├── 1. 現在の active_plugins を取得
│   │
│   ├── 2. フック発火（$silent = false の場合）
│   │   ├── do_action('deactivate_plugin', $plugin, $network_deactivating)
│   │   ├── do_action('deactivate_' . $plugin, $network_deactivating)
│   │   │   └── ※ register_deactivation_hook() のコールバックはここで実行
│   │   └── ※ この時点ではプラグインはまだアクティブ
│   │
│   ├── 3. active_plugins からプラグインを除去
│   │   └── update_option('active_plugins', ...)
│   │
│   ├── 4. recovery mode からの除去
│   │   └── wp_paused_plugins()->delete($plugin)
│   │
│   └── 5. do_action('deactivated_plugin', $plugin, $network_deactivating)
│
└── recently_activated オプションに追加
```

### `register_deactivation_hook()`

```php
function register_deactivation_hook(string $file, callable $callback): void {
    $file = plugin_basename($file);
    add_action('deactivate_' . $file, $callback);
}
```

> [!IMPORTANT]
> 無効化コールバック実行時、プラグインは**まだアクティブ**です。これはプラグインの関数やクラスを使える最後の機会です。

### 無効化時の典型的な処理

```php
register_deactivation_hook(__FILE__, function (): void {
    // スケジュールされた Cron イベントの削除
    wp_clear_scheduled_hook('my_plugin_cron_event');

    // Rewrite ルールのフラッシュ
    flush_rewrite_rules();

    // 一時データの削除（テーブルやオプションは残す）
    delete_transient('my_plugin_cache');
});
```

## 6. アンインストール

WordPress はプラグインのアンインストール（削除）時に 2 つの方法でクリーンアップ処理を提供します:

### 方法 1: `uninstall.php` ファイル

プラグインディレクトリのルートに `uninstall.php` を配置する方法。最も推奨される方法です。

```php
// my-plugin/uninstall.php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;  // 直接アクセス防止
}

// データベーステーブルの削除
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}my_table");

// オプションの削除
delete_option('my_plugin_version');
delete_option('my_plugin_settings');

// Transient の削除
delete_transient('my_plugin_cache');

// ユーザーメタの削除
delete_metadata('user', 0, 'my_plugin_meta', '', true);
```

### 方法 2: `register_uninstall_hook()`

```php
register_uninstall_hook(string $file, callable $callback): bool
```

- コールバックは `uninstall_plugins` オプションに保存される
- `uninstall.php` が存在する場合はそちらが優先される
- コールバックは**静的メソッドまたは関数**でなければならない（インスタンスメソッドは保存できない）

```php
register_uninstall_hook(__FILE__, ['MyPlugin', 'uninstall']);

class MyPlugin {
    public static function uninstall(): void {
        delete_option('my_plugin_settings');
    }
}
```

### アンインストールフロー

```
delete_plugins(['my-plugin/my-plugin.php'])  ← wp-admin/plugins.php から呼ばれる
│
├── 各プラグインに対して:
│   │
│   ├── プラグインが有効な場合 → 先に無効化
│   │
│   ├── uninstall.php の存在チェック
│   │   ├── 存在する場合
│   │   │   ├── define('WP_UNINSTALL_PLUGIN', $plugin)
│   │   │   └── include(uninstall.php)
│   │   │
│   │   └── 存在しない場合
│   │       ├── uninstall_plugins オプションを確認
│   │       └── 登録されたコールバックを実行
│   │
│   └── プラグインファイルの物理削除
│
└── do_action('deleted_plugin', $plugin, $deleted)
```

### 各フェーズで削除すべきデータ

| フェーズ | 削除対象 | 例 |
|---|---|---|
| 無効化 | 一時データのみ | Cron イベント、Transient、Rewrite ルール |
| アンインストール | すべてのデータ | DB テーブル、オプション、ユーザーメタ、投稿メタ、ファイル |

## 7. フック一覧

### Action

| フック名 | パラメータ | 説明 |
|---|---|---|
| `activate_plugin` | `(string $plugin, bool $network_wide)` | プラグイン有効化前（v2.9+） |
| `activate_{$plugin}` | `(bool $network_wide)` | 特定プラグインの有効化時 |
| `activated_plugin` | `(string $plugin, bool $network_wide)` | プラグイン有効化後 |
| `deactivate_plugin` | `(string $plugin, bool $network_deactivating)` | プラグイン無効化前 |
| `deactivate_{$plugin}` | `(bool $network_deactivating)` | 特定プラグインの無効化時 |
| `deactivated_plugin` | `(string $plugin, bool $network_deactivating)` | プラグイン無効化後 |
| `uninstall_{$plugin}` | なし | プラグインアンインストール時 |
| `deleted_plugin` | `(string $plugin_file, bool $deleted)` | プラグイン削除後 |
| `muplugins_loaded` | なし | Must-Use プラグインのロード完了後 |
| `plugins_loaded` | なし | 全プラグインのロード完了後 |
| `plugin_loaded` | `(string $plugin)` | 各プラグインのロード完了後（v6.1+） |

### Filter

| フック名 | パラメータ | 説明 |
|---|---|---|
| `option_active_plugins` | `(array $plugins)` | 有効プラグイン一覧のフィルタリング |
| `pre_update_option_active_plugins` | `(array $plugins, array $old_plugins, string $option)` | 有効プラグイン更新前 |
| `plugin_action_links_{$plugin}` | `(array $actions, string $plugin_file, array $plugin_data, string $context)` | プラグイン一覧のアクションリンク |
| `network_admin_plugin_action_links_{$plugin}` | `(array $actions, string $plugin_file, array $plugin_data, string $context)` | ネットワーク管理のアクションリンク |
| `all_plugins` | `(array $all_plugins)` | プラグイン一覧ページの全プラグインデータ |

## 8. リカバリーモード

WordPress 5.2 で導入されたリカバリーモードは、プラグインの致命的エラーからサイトを復旧するための仕組みです。

```
リクエスト中にプラグインが致命的エラーを発生
│
├── WP_Recovery_Mode が検出
├── wp_paused_plugins にプラグインを登録
├── 管理者にリカバリーモード URL をメール送信
│
└── 次回リクエスト
    ├── リカバリーモード: 問題のプラグインをスキップしてロード
    └── 管理者がプラグインを無効化・修正
```

| 関連関数 | 説明 |
|---|---|
| `wp_is_recovery_mode_active()` | リカバリーモードが有効か |
| `wp_paused_plugins()` | 一時停止されたプラグインの管理 |
| `is_plugin_paused()` | 指定プラグインが一時停止中か |
