# Option Component

**Package:** `wppack/option`
**Namespace:** `WpPack\Component\Option\`
**Layer:** Infrastructure

WordPress オプション API のモダンなオブジェクト指向ラッパーです。型安全なオプションアクセス、アトリビュートベースのプロパティマッピング、バリデーション、暗号化サポート、およびオプション関連の WordPress フックの名前付きフックアトリビュートを提供します。

## インストール

```bash
composer require wppack/option
```

## 基本コンセプト

### 従来の WordPress と WpPack の比較

```php
// 従来の WordPress - 型なし、散在するオプションアクセス
$settings = get_option('my_plugin_settings', []);
$apiKey = $settings['api_key'] ?? '';
$debug = $settings['debug'] ?? false;
$maxRetries = isset($settings['max_retries']) ? (int) $settings['max_retries'] : 3;

update_option('my_plugin_settings', array_merge($settings, ['api_key' => 'new-key']));

// WpPack Option - 型安全で構造化されたオプション管理
use WpPack\Component\Option\AbstractOption;
use WpPack\Component\Option\Attribute\Property;

final class MyPluginSettings extends AbstractOption
{
    protected static string $optionName = 'my_plugin_settings';

    #[Property]
    public string $apiKey = '';

    #[Property]
    public bool $debug = false;

    #[Property]
    public int $maxRetries = 3;
}

// 使用例
$settings = $container->get(MyPluginSettings::class);
$settings->apiKey;       // string - 型安全
$settings->debug;        // bool - 型安全
$settings->maxRetries;   // int - 型安全

$settings->apiKey = 'new-key';
$settings->save();
```

## AbstractOption クラス

`AbstractOption` を継承し、`#[Property]` アトリビュートでオプションクラスを定義します：

```php
use WpPack\Component\Option\AbstractOption;
use WpPack\Component\Option\Attribute\Property;
use WpPack\Component\Option\Attribute\OptionName;

#[OptionName('app_settings')]
final class AppSettings extends AbstractOption
{
    #[Property]
    public string $siteName = 'My Site';

    #[Property]
    public string $tagline = '';

    #[Property]
    public bool $maintenanceMode = false;

    #[Property]
    public int $postsPerPage = 10;

    #[Property]
    public array $allowedIps = [];
}
```

### 読み書き

```php
$settings = $container->get(AppSettings::class);

// 値の読み取り（型安全）
$name = $settings->siteName;           // string
$maintenance = $settings->maintenanceMode;  // bool
$perPage = $settings->postsPerPage;    // int

// 値の書き込み
$settings->maintenanceMode = true;
$settings->postsPerPage = 20;
$settings->save();

// デフォルト値にリセット
$settings->reset();
```

## バリデーション

オプションプロパティにバリデーションルールを追加します。`#[OptionValidate]` は `register_setting()` の `sanitize_callback` にマッピングされ、Validator コンポーネントの `#[Validate]` とは異なります。

```php
use WpPack\Component\Option\AbstractOption;
use WpPack\Component\Option\Attribute\Property;
use WpPack\Component\Option\Attribute\OptionValidate;

final class ApiSettings extends AbstractOption
{
    protected static string $optionName = 'api_settings';

    #[Property]
    #[OptionValidate(notEmpty: true)]
    public string $apiEndpoint = 'https://api.example.com';

    #[Property]
    #[OptionValidate(min: 1, max: 100)]
    public int $requestTimeout = 30;

    #[Property]
    #[OptionValidate(pattern: '/^[a-zA-Z0-9]{32}$/')]
    public string $apiKey = '';

    #[Property]
    #[OptionValidate(in: ['json', 'xml'])]
    public string $responseFormat = 'json';
}

// バリデーションは保存時に自動的に実行されます
$settings = $container->get(ApiSettings::class);
$settings->requestTimeout = 200; // 無効: 最大値を超過
$settings->save(); // ValidationException をスロー
```

## 暗号化オプション

機密性の高い値を暗号化して保存します：

```php
use WpPack\Component\Option\Attribute\Encrypted;

final class CredentialSettings extends AbstractOption
{
    protected static string $optionName = 'credentials';

    #[Property]
    #[Encrypted]
    public string $secretKey = '';

    #[Property]
    #[Encrypted]
    public string $apiToken = '';

    #[Property]
    public string $publicId = '';
}

// 値はデータベースに暗号化して保存され、読み取り時に復号されます
$creds = $container->get(CredentialSettings::class);
$creds->secretKey = 'my-secret-key';
$creds->save();  // wp_options に暗号化して保存

echo $creds->secretKey; // 'my-secret-key'（復号済み）
```

## 複合データ型

配列やネストされたデータ構造を扱います：

```php
final class NotificationSettings extends AbstractOption
{
    protected static string $optionName = 'notification_settings';

    #[Property]
    public array $enabledChannels = ['email'];

    #[Property]
    public array $recipients = [];

    #[Property]
    public array $schedule = [
        'frequency' => 'daily',
        'time' => '09:00',
        'timezone' => 'UTC',
    ];
}
```

## オプショングループ

関連するオプションをグループにまとめます：

```php
use WpPack\Component\Option\OptionGroup;

final class PluginOptions extends OptionGroup
{
    public function __construct(
        public readonly GeneralSettings $general,
        public readonly ApiSettings $api,
        public readonly NotificationSettings $notifications,
    ) {}
}

// 単一のエントリポイントからすべての設定にアクセス
$options = $container->get(PluginOptions::class);
$options->general->siteName;
$options->api->apiEndpoint;
$options->notifications->enabledChannels;
```

## マルチサイト対応

サイト固有のオプション名を通じて WordPress マルチサイトに対応しています：

```php
final class SiteSettings extends AbstractOption
{
    protected static string $optionName = 'site_settings';
    protected static bool $networkOption = false; // サイト別オプション（デフォルト）
}

final class NetworkSettings extends AbstractOption
{
    protected static string $optionName = 'network_settings';
    protected static bool $networkOption = true; // ネットワーク全体のオプション
}

// ネットワークオプションは get_site_option() / update_site_option() を使用
```

## 名前付きフックアトリビュート

Option コンポーネントは WordPress のオプションフック用の名前付きフックアトリビュートを提供します。

### オプション読み取りフック

#### #[PreOptionFilter]

データベースから読み取る前にオプション値をインターセプトします。

**WordPress Hook:** `pre_option_{$option}`

```php
use WpPack\Component\Option\Attribute\PreOptionFilter;

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
use WpPack\Component\Option\Attribute\OptionFilter;

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
use WpPack\Component\Option\Attribute\DefaultOptionFilter;

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
use WpPack\Component\Option\Attribute\PreUpdateOptionFilter;

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
use WpPack\Component\Option\Attribute\UpdateOptionAction;

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
use WpPack\Component\Option\Attribute\AddOptionAction;

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
use WpPack\Component\Option\Attribute\DeleteOptionAction;

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
use WpPack\Component\Option\Attribute\PreSiteOptionFilter;
use WpPack\Component\Option\Attribute\SiteOptionFilter;
use WpPack\Component\Option\Attribute\UpdateSiteOptionAction;

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
| `AbstractOption` | 型付きオプション定義の基底クラス |
| `OptionGroup` | 関連するオプションクラスをグループ化 |
| `Attribute\Property` | プロパティを保存対象のオプションフィールドとしてマーク |
| `Attribute\OptionName` | WordPress のオプション名を指定 |
| `Attribute\OptionValidate` | オプションプロパティのバリデーションルール（`register_setting()` の `sanitize_callback` にマッピング） |
| `Attribute\Encrypted` | プロパティを暗号化保存としてマーク |
| `Attribute\PreOptionFilter` | 読み取り前オプションフィルターフック |
| `Attribute\OptionFilter` | 読み取り後オプションフィルターフック |
| `Attribute\PreUpdateOptionFilter` | 保存前オプションフィルターフック |
| `Attribute\UpdateOptionAction` | 更新後オプションアクションフック |
| `Attribute\AddOptionAction` | オプション作成アクションフック |
| `Attribute\DeleteOptionAction` | オプション削除アクションフック |
