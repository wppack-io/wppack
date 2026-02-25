# Setting コンポーネント

**パッケージ:** `wppack/setting`
**名前空間:** `WpPack\Component\Setting\`
**レイヤー:** Application

WordPress の設定管理を型安全かつオブジェクト指向で行うコンポーネントです。管理画面の自動生成、バリデーション、サニタイゼーション、タブ付きインターフェースを提供します。

## インストール

```bash
composer require wppack/setting
```

## 基本コンセプト

### 従来の WordPress コード

```php
add_action('admin_init', 'my_plugin_settings_init');
function my_plugin_settings_init() {
    register_setting('my_plugin_settings', 'my_plugin_options');
    add_settings_section('general', 'General Settings', null, 'my_plugin_settings');
    add_settings_field('api_key', 'API Key', 'api_key_callback', 'my_plugin_settings', 'general');
}
// フォーム、バリデーション、保存のために50行以上の手続き型コードが必要...
```

### WpPack コード

```php
use WpPack\Component\Setting\AbstractSettings;
use WpPack\Component\Setting\Attribute\Setting;
use WpPack\Component\Setting\Attribute\SettingField;
use WpPack\Component\Setting\Attribute\SettingValidate;

#[Setting(
    id: 'my_plugin_settings',
    label: 'My Plugin Settings',
    menuTitle: 'My Plugin'
)]
class PluginSettings extends AbstractSettings
{
    #[SettingField(type: 'text', label: 'API Key')]
    #[SettingValidate('required|alphanumeric|length:32')]
    protected string $apiKey = '';
}
```

## 機能

- **管理画面の自動生成** - クラスアトリビュートからページを自動生成
- **型安全な設定プロパティ** - PHP 8 の型宣言を活用
- **組み込みバリデーションとサニタイゼーション** - アトリビュートによる定義
- **タブ付き設定インターフェース** - 複雑な設定の整理
- **変更追跡と通知** - 重要な設定変更の検知
- **セキュリティ** - 自動 nonce 検証、権限チェック、入力サニタイゼーション

## クイックスタート

### 最初の設定ページ

```php
<?php
use WpPack\Component\Setting\AbstractSettings;
use WpPack\Component\Setting\Attribute\Setting;
use WpPack\Component\Setting\Attribute\SettingField;
use WpPack\Component\Setting\Attribute\SettingSection;
use WpPack\Component\Setting\Attribute\SettingValidate;

#[Setting(
    id: 'social_media_settings',
    label: 'Social Media Plugin Settings',
    menuTitle: 'Social Media',
    capability: 'manage_options',
    position: 30
)]
class SocialMediaSettings extends AbstractSettings
{
    #[SettingSection('general', 'General Settings')]
    #[SettingField(
        type: 'text',
        label: 'Plugin Name',
        description: 'Display name for your social media integration',
        default: 'My Social Media'
    )]
    protected string $pluginName;

    #[SettingSection('general')]
    #[SettingField(
        type: 'checkbox',
        label: 'Enable Plugin',
        description: 'Turn on/off all social media features'
    )]
    protected bool $enabled = true;

    #[SettingSection('api', 'API Configuration')]
    #[SettingField(
        type: 'text',
        label: 'Facebook App ID',
        description: 'Your Facebook application ID'
    )]
    #[SettingValidate('required|alphanumeric')]
    protected string $facebookAppId = '';

    #[SettingSection('api')]
    #[SettingField(
        type: 'password',
        label: 'Facebook App Secret',
        description: 'Your Facebook application secret (stored securely)'
    )]
    #[SettingValidate('required|min:10')]
    protected string $facebookAppSecret = '';

    #[SettingSection('display', 'Display Options')]
    #[SettingField(
        type: 'select',
        label: 'Default Share Style',
        options: [
            'buttons' => 'Share Buttons',
            'icons' => 'Icon Only',
            'text' => 'Text Links'
        ],
        default: 'buttons'
    )]
    protected string $shareStyle;

    #[SettingSection('display')]
    #[SettingField(
        type: 'color',
        label: 'Button Color',
        default: '#1877f2'
    )]
    protected string $buttonColor;

    #[SettingSection('display')]
    #[SettingField(
        type: 'multiselect',
        label: 'Enabled Platforms',
        options: [
            'facebook' => 'Facebook',
            'twitter' => 'Twitter',
            'linkedin' => 'LinkedIn',
            'instagram' => 'Instagram'
        ],
        description: 'Select which social platforms to enable'
    )]
    protected array $enabledPlatforms = ['facebook', 'twitter'];

    #[SettingSection('advanced', 'Advanced Settings')]
    #[SettingField(
        type: 'number',
        label: 'Cache Duration',
        description: 'How long to cache social media data (seconds)',
        min: 60,
        max: 86400,
        default: 3600
    )]
    protected int $cacheDuration;

    #[SettingSection('advanced')]
    #[SettingField(
        type: 'textarea',
        label: 'Custom CSS',
        description: 'Additional CSS for social media elements',
        rows: 5
    )]
    protected string $customCss = '';

    protected function onSave(array $oldValues, array $newValues): void
    {
        if ($oldValues['enabled'] !== $newValues['enabled']) {
            if ($newValues['enabled']) {
                $this->setupSocialMediaFeatures();
            } else {
                $this->cleanupSocialMediaFeatures();
            }
        }

        if ($oldValues['cacheDuration'] !== $newValues['cacheDuration']) {
            wp_cache_flush();
        }
    }
}
```

### 設定の登録

```php
<?php
add_action('admin_init', function() {
    $container = new WpPack\Container();
    $container->register(SocialMediaSettings::class);
});
```

### 設定へのアクセス

```php
<?php
$settings = new SocialMediaSettings();

if ($settings->enabled) {
    echo 'Plugin is enabled!';
}

echo 'Share style: ' . $settings->shareStyle;
echo 'Button color: ' . $settings->buttonColor;

if (in_array('facebook', $settings->enabledPlatforms)) {
    $appId = $settings->facebookAppId;
}
```

### タブ付き設定

```php
use WpPack\Component\Setting\Attribute\SettingTab;

#[SettingTab('general', 'General', icon: 'dashicons-admin-generic')]
#[SettingSection('basic', 'Basic Settings')]
#[SettingField(type: 'text', label: 'Site Name')]
protected string $siteName;

#[SettingTab('advanced', 'Advanced', icon: 'dashicons-admin-tools')]
#[SettingSection('performance', 'Performance')]
#[SettingField(type: 'number', label: 'Cache Duration')]
protected int $cacheDuration;
```

### カスタムバリデーション

```php
protected function validate(): array
{
    $errors = parent::validate();

    if ($this->facebookAppId && !$this->facebookAppSecret) {
        $errors['facebookAppSecret'] = 'App Secret is required when App ID is provided';
    }

    return $errors;
}
```

## フィールドタイプ

### 基本入力フィールド

#### Text フィールド

```php
#[SettingField(
    type: 'text',
    label: 'Site Title',
    default: 'My Website',
    placeholder: 'Enter site title...',
    maxlength: 100
)]
protected string $siteTitle;
```

**オプション:** `placeholder`, `maxlength`, `readonly`

#### Textarea フィールド

```php
#[SettingField(
    type: 'textarea',
    label: 'Site Description',
    rows: 4,
    cols: 50,
    default: 'A great WordPress site'
)]
protected string $siteDescription;
```

#### Email フィールド

```php
#[SettingField(type: 'email', label: 'Admin Email', placeholder: 'admin@example.com')]
#[SettingValidate('required|email')]
protected string $adminEmail;
```

#### URL フィールド

```php
#[SettingField(type: 'url', label: 'Company Website', placeholder: 'https://example.com')]
#[SettingValidate('url')]
protected string $companyWebsite;
```

#### Password フィールド

```php
#[SettingField(
    type: 'password',
    label: 'API Secret Key',
    description: 'This will be stored securely'
)]
#[SettingValidate('required|min:10')]
protected string $apiSecret;
```

#### Number フィールド

```php
#[SettingField(
    type: 'number',
    label: 'Max Posts Per Page',
    min: 1,
    max: 100,
    step: 1,
    default: 10
)]
protected int $postsPerPage;
```

### 選択フィールド

#### Checkbox

```php
#[SettingField(
    type: 'checkbox',
    label: 'Enable Comments',
    description: 'Allow users to comment on posts',
    default: true
)]
protected bool $enableComments;
```

#### Radio ボタン

```php
#[SettingField(
    type: 'radio',
    label: 'Date Format',
    options: [
        'Y-m-d' => '2024-01-15',
        'm/d/Y' => '01/15/2024',
        'd/m/Y' => '15/01/2024',
        'F j, Y' => 'January 15, 2024'
    ],
    default: 'Y-m-d'
)]
protected string $dateFormat;
```

#### Select ドロップダウン

```php
#[SettingField(
    type: 'select',
    label: 'Default Post Status',
    options: [
        'draft' => 'Draft',
        'pending' => 'Pending Review',
        'publish' => 'Published'
    ],
    default: 'draft'
)]
protected string $defaultPostStatus;
```

**動的オプション：**

```php
#[SettingField(
    type: 'select',
    label: 'Default Category',
    options: 'getCategoryOptions'
)]
protected int $defaultCategory;

protected function getCategoryOptions(): array
{
    $categories = get_categories(['hide_empty' => false]);
    $options = [0 => 'No Category'];

    foreach ($categories as $category) {
        $options[$category->term_id] = $category->name;
    }

    return $options;
}
```

#### Multi-Select

```php
#[SettingField(
    type: 'multiselect',
    label: 'Enabled Post Types',
    options: [
        'post' => 'Posts',
        'page' => 'Pages',
        'product' => 'Products',
        'event' => 'Events'
    ],
    size: 4
)]
protected array $enabledPostTypes = ['post', 'page'];
```

### カラーピッカー

```php
#[SettingField(type: 'color', label: 'Brand Color', default: '#0073aa')]
protected string $brandColor;
```

### フィールドバリデーション

```php
use WpPack\Component\Setting\Attribute\SettingValidate;

#[SettingField(type: 'text', label: 'Username')]
#[SettingValidate('required|alphanumeric|min:3|max:20')]
protected string $username;

#[SettingField(type: 'url', label: 'Website')]
#[SettingValidate('url|starts_with:https')]
protected string $website;

#[SettingField(type: 'number', label: 'Port')]
#[SettingValidate('required|integer|min:1|max:65535')]
protected int $port;
```

**利用可能なバリデーションルール：**
- `required` - 値が必須
- `email` - 有効なメールアドレスであること
- `url` - 有効な URL であること
- `alphanumeric` - 英数字のみ
- `min:X` / `max:X` - 最小/最大の長さまたは値
- `length:X` - 正確な長さ
- `regex:/pattern/` - 正規表現パターンに一致
- `starts_with:prefix` / `ends_with:suffix` - 文字列のプレフィックス/サフィックス
- `in:value1,value2` - 指定された値のいずれかであること

### カスタムフィールドタイプ

```php
use WpPack\Component\Setting\Fields\AbstractField;

class LocationField extends AbstractField
{
    public function render(): string
    {
        return '<div class="location-picker">
            <input type="text" name="' . $this->getName() . '[address]" />
            <input type="hidden" name="' . $this->getName() . '[lat]" />
            <input type="hidden" name="' . $this->getName() . '[lng]" />
            <div class="map-container"></div>
        </div>';
    }

    public function sanitize($value): array
    {
        return [
            'address' => sanitize_text_field($value['address'] ?? ''),
            'lat' => floatval($value['lat'] ?? 0),
            'lng' => floatval($value['lng'] ?? 0)
        ];
    }
}

#[SettingField(type: LocationField::class, label: 'Business Location')]
protected array $businessLocation;
```

## Named Hook アトリビュート

### #[SettingsPageAction(priority?: int = 10)]

**WordPress フック:** `load-{$page_hook}`

```php
use WpPack\Component\Setting\Attribute\SettingsPageAction;

class SettingsPageManager
{
    #[SettingsPageAction(page: 'settings_page_wppack-settings')]
    public function onSettingsPageLoad(): void
    {
        $screen = get_current_screen();

        $screen->add_help_tab([
            'id' => 'wppack_overview',
            'title' => __('Overview', 'wppack'),
            'content' => $this->getHelpContent('overview'),
        ]);

        add_screen_option('layout_columns', [
            'max' => 2,
            'default' => 2,
        ]);

        $this->initializeSettingsFields();
    }
}
```

## Hook アトリビュートリファレンス

```php
// 設定ページ
#[SettingsPageAction(priority?: int = 10)]     // 設定ページ読み込み時
```

## このコンポーネントの使用場面

**最適な用途：**
- プラグイン設定ページ
- テーマカスタマイズオプション
- 管理者専用の設定インターフェース
- 複雑なマルチタブ設定
- バリデーションが必要な設定

**代替を検討すべき場合：**
- シンプルなテーマカスタマイザーオプション（WordPress カスタマイザーを使用）
- ユーザー固有の設定（ユーザーメタを使用）
- 一時的な設定（Transient を使用）

## WordPress 統合

- 設定は通常の WordPress 管理画面ページとして**設定**メニューに表示
- **WordPress のユーザーロールと権限**と互換性あり
- **マルチサイトネットワーク**でのネットワーク全体の設定に対応
- **WordPress Settings API**との互換性を維持
- **WordPress 管理画面のカラースキーム**とレスポンシブデザインに対応

## 依存関係

### 必須
- **なし** - WordPress Settings API のみで動作

### 推奨
- **Option コンポーネント** - 拡張された設定ストレージ
- **Security コンポーネント** - 入力サニタイゼーションと権限チェック
- **Hook コンポーネント** - 設定登録フック
