# Option Component

**Package:** `wppack/option`
**Namespace:** `WPPack\Component\Option\`
**Layer:** Infrastructure

WordPress オプション API のモダンなオブジェクト指向ラッパーです。シンプルなマネージャークラスによるオプション操作と、オプション関連の WordPress フックの名前付きフックアトリビュートを提供します。

## インストール

```bash
composer require wppack/option
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// 従来の WordPress - グローバル関数の直接呼び出し
$value = get_option('my_plugin_settings', []);
update_option('my_plugin_settings', ['key' => 'value']);
add_option('my_plugin_new_option', 'default');
delete_option('my_plugin_old_option');

// マルチサイト
$networkValue = get_site_option('network_settings');
update_site_option('network_settings', ['key' => 'value']);
```

### After（WPPack）

```php
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Option\SiteOptionManager;

// DI コンテナ経由で注入
public function __construct(
    private readonly OptionManager $option,
    private readonly SiteOptionManager $siteOption,
) {}

// サイトオプション
$value = $this->option->get('my_plugin_settings', []);
$this->option->update('my_plugin_settings', ['key' => 'value']);
$this->option->add('my_plugin_new_option', 'default');
$this->option->delete('my_plugin_old_option');

// マルチサイト（ネットワークオプション）
$networkValue = $this->siteOption->get('network_settings');
$this->siteOption->update('network_settings', ['key' => 'value']);
```

## OptionManager

サイト単位の WordPress オプション（`wp_options` テーブル）を操作します。

### メソッド一覧

| メソッド | 説明 | WordPress 関数 |
|---------|------|----------------|
| `get(string $option, mixed $default = false): mixed` | オプション値を取得 | `get_option()` |
| `add(string $option, mixed $value = '', ?bool $autoload = null): bool` | オプションを新規追加 | `add_option()` |
| `update(string $option, mixed $value, ?bool $autoload = null): bool` | オプションを更新（存在しなければ作成） | `update_option()` |
| `delete(string $option): bool` | オプションを削除 | `delete_option()` |

### 使用例

```php
use WPPack\Component\Option\OptionManager;

$option = new OptionManager();

// 値の取得（存在しなければデフォルト値）
$settings = $option->get('my_plugin_settings', []);

// 新規追加（既に存在する場合は false を返す）
$option->add('my_plugin_version', '1.0.0');

// 更新（存在しなければ作成）
$option->update('my_plugin_settings', ['debug' => true]);

// autoload を制御
$option->add('my_large_data', $data, false);       // autoload しない
$option->update('my_setting', 'value', true);       // autoload する
$option->update('my_setting', 'value');              // autoload を変更しない

// 削除
$option->delete('my_plugin_old_setting');
```

## SiteOptionManager

マルチサイト環境のネットワーク全体オプション（`wp_sitemeta` テーブル）を操作します。シングルサイト環境では `get_option()` / `update_option()` / `delete_option()` へフォールバックします。

### メソッド一覧

| メソッド | 説明 | WordPress 関数 |
|---------|------|----------------|
| `get(string $option, mixed $default = false): mixed` | サイトオプション値を取得 | `get_site_option()` |
| `update(string $option, mixed $value): bool` | サイトオプションを更新（存在しなければ作成） | `update_site_option()` |
| `delete(string $option): bool` | サイトオプションを削除 | `delete_site_option()` |

> [!NOTE]
> `SiteOptionManager` には `add()` メソッドはありません。`update_site_option()` は存在しない場合に自動的に作成するため不要です。

### 使用例

```php
use WPPack\Component\Option\SiteOptionManager;

$siteOption = new SiteOptionManager();

// ネットワーク全体の設定を取得
$networkSettings = $siteOption->get('network_settings', []);

// 更新（存在しなければ作成）
$siteOption->update('network_settings', ['maintenance' => false]);

// 削除
$siteOption->delete('network_old_setting');
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Option](../hook/option.md) を参照してください。

## 主要クラス

| クラス | 説明 |
|-------|------|
| `OptionManager` | サイト単位のオプション操作マネージャー |
| `SiteOptionManager` | ネットワーク全体のオプション操作マネージャー |
