# Option Component

**Package:** `wppack/option`
**Namespace:** `WpPack\Component\Option\`
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

### After（WpPack）

```php
use WpPack\Component\Option\OptionManager;
use WpPack\Component\Option\SiteOptionManager;

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
use WpPack\Component\Option\OptionManager;

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
use WpPack\Component\Option\SiteOptionManager;

$siteOption = new SiteOptionManager();

// ネットワーク全体の設定を取得
$networkSettings = $siteOption->get('network_settings', []);

// 更新（存在しなければ作成）
$siteOption->update('network_settings', ['maintenance' => false]);

// 削除
$siteOption->delete('network_old_setting');
```

## 名前付きフックアトリビュート

Option コンポーネントは WordPress のオプションフック用の名前付きフックアトリビュートを提供します。

### オプション読み取りフック

#### #[PreOptionFilter]

データベースから読み取る前にオプション値をインターセプトします。

**WordPress Hook:** `pre_option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Filter\PreOptionFilter;

class OptionInterceptor
{
    #[PreOptionFilter('blogname', priority: 10)]
    public function filterSiteName(mixed $preValue): mixed
    {
        // 値を返すとデータベース検索をショートサーキットします
        if (defined('SITE_NAME_OVERRIDE')) {
            return SITE_NAME_OVERRIDE;
        }

        // false を返すと通常のデータベース検索を続行します
        return false;
    }
}
```

#### #[OptionFilter]

データベースから読み取った後にオプション値をフィルタリングします。

**WordPress Hook:** `option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Filter\OptionFilter;

class OptionProcessor
{
    #[OptionFilter('blogdescription', priority: 10)]
    public function filterDescription(mixed $value): mixed
    {
        if (empty($value)) {
            return 'Default site description';
        }

        return $value;
    }
}
```

#### #[DefaultOptionFilter]

データベースに存在しないオプションのデフォルト値を提供します。

**WordPress Hook:** `default_option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Filter\DefaultOptionFilter;

class OptionDefaults
{
    #[DefaultOptionFilter('my_plugin_settings', priority: 10)]
    public function provideDefaults(mixed $default, string $option, bool $passedDefault): mixed
    {
        if (!$passedDefault) {
            return [
                'version' => '1.0.0',
                'enabled' => true,
                'features' => ['basic'],
            ];
        }

        return $default;
    }
}
```

### オプション書き込みフック

#### #[PreUpdateOptionFilter]

保存前にオプション値を検証または変更します。

**WordPress Hook:** `pre_update_option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Filter\PreUpdateOptionFilter;

class OptionValidator
{
    #[PreUpdateOptionFilter('my_plugin_settings', priority: 10)]
    public function validateBeforeSave(mixed $value, mixed $oldValue, string $option): mixed
    {
        // 値をサニタイズ
        if (is_array($value)) {
            $value['api_key'] = sanitize_text_field($value['api_key'] ?? '');
            $value['max_retries'] = max(1, min(10, (int) ($value['max_retries'] ?? 3)));
        }

        return $value;
    }
}
```

#### #[UpdateOptionAction]

オプション更新後にアクションを実行します。

**WordPress Hook:** `update_option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Action\UpdateOptionAction;

class OptionChangeHandler
{
    #[UpdateOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsUpdated(mixed $oldValue, mixed $newValue, string $option): void
    {
        // キャッシュをクリア
        wp_cache_flush_group('my_plugin');

        // 変更をログに記録
        $this->logger->info('Settings updated', [
            'option' => $option,
            'changed_by' => get_current_user_id(),
        ]);
    }
}
```

#### #[AddOptionAction]

オプションが初めて作成されたときにアクションを実行します。

**WordPress Hook:** `add_option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Action\AddOptionAction;

class OptionCreationHandler
{
    #[AddOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsCreated(string $option, mixed $value): void
    {
        $this->logger->info('Plugin settings initialized', [
            'option' => $option,
        ]);
    }
}
```

#### #[DeleteOptionAction]

オプションが削除されたときにアクションを実行します。

**WordPress Hook:** `delete_option_{$option}`

```php
use WpPack\Component\Hook\Attribute\Option\Action\DeleteOptionAction;

class OptionDeletionHandler
{
    #[DeleteOptionAction('my_plugin_settings', priority: 10)]
    public function onSettingsDeleted(string $option): void
    {
        // 関連データをクリーンアップ
        delete_transient('my_plugin_cache');
        $this->logger->info('Plugin settings deleted');
    }
}
```

### サイトオプションフック（マルチサイト）

```php
use WpPack\Component\Hook\Attribute\Option\Filter\PreSiteOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Filter\SiteOptionFilter;
use WpPack\Component\Hook\Attribute\Option\Action\UpdateSiteOptionAction;

class NetworkOptionHandler
{
    #[PreSiteOptionFilter('network_settings', priority: 10)]
    public function filterNetworkOption(mixed $preValue): mixed
    {
        // ネットワークオプション検索をショートサーキット
        return false;
    }

    #[SiteOptionFilter('network_settings', priority: 10)]
    public function processNetworkOption(mixed $value): mixed
    {
        return $value;
    }

    #[UpdateSiteOptionAction('network_settings', priority: 10)]
    public function onNetworkSettingsUpdated(mixed $oldValue, mixed $newValue): void
    {
        // ネットワーク内の全サイトに反映
    }
}
```

## フックアトリビュートリファレンス

```php
// オプション読み取り
#[PreOptionFilter('option_name', priority: 10)]      // データベース読み取り前
#[OptionFilter('option_name', priority: 10)]         // データベース読み取り後
#[DefaultOptionFilter('option_name', priority: 10)]  // デフォルト値プロバイダー

// オプション書き込み
#[PreUpdateOptionFilter('option_name', priority: 10)]  // 保存前（バリデーション）
#[UpdateOptionAction('option_name', priority: 10)]     // 更新後
#[AddOptionAction('option_name', priority: 10)]        // 初回作成後
#[DeleteOptionAction('option_name', priority: 10)]     // 削除後

// マルチサイト（サイトオプション）
#[PreSiteOptionFilter('option_name', priority: 10)]    // サイトオプション読み取り前
#[SiteOptionFilter('option_name', priority: 10)]       // サイトオプション読み取り後
#[UpdateSiteOptionAction('option_name', priority: 10)] // サイトオプション更新後
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `OptionManager` | サイト単位のオプション操作マネージャー |
| `SiteOptionManager` | ネットワーク全体のオプション操作マネージャー |
| `Attribute\Action\AddOptionAction` | オプション作成アクションフック |
| `Attribute\Action\DeleteOptionAction` | オプション削除アクションフック |
| `Attribute\Action\UpdateOptionAction` | 更新後オプションアクションフック |
| `Attribute\Action\UpdateSiteOptionAction` | サイトオプション更新アクションフック |
| `Attribute\Filter\DefaultOptionFilter` | デフォルト値オプションフィルターフック |
| `Attribute\Filter\OptionFilter` | 読み取り後オプションフィルターフック |
| `Attribute\Filter\PreOptionFilter` | 読み取り前オプションフィルターフック |
| `Attribute\Filter\PreSiteOptionFilter` | サイトオプション読み取り前フィルターフック |
| `Attribute\Filter\PreUpdateOptionFilter` | 保存前オプションフィルターフック |
| `Attribute\Filter\SiteOptionFilter` | サイトオプション読み取り後フィルターフック |
