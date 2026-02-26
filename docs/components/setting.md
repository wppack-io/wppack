# Setting コンポーネント

**パッケージ:** `wppack/setting`
**名前空間:** `WpPack\Component\Setting\`
**レイヤー:** Application

WordPress Settings API をモダンな PHP で扱うためのコンポーネントです。アトリビュートによる設定登録と Named Hook アトリビュートを提供します。

## インストール

```bash
composer require wppack/setting
```

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('admin_init', 'my_plugin_settings_init');
function my_plugin_settings_init() {
    register_setting('my_plugin_settings', 'my_plugin_options');
    add_settings_section('general', 'General Settings', null, 'my_plugin_settings');
    add_settings_field('api_key', 'API Key', 'api_key_callback', 'my_plugin_settings', 'general');
}
// フォーム、バリデーション、保存のために50行以上の手続き型コードが必要...
```

### After（WpPack）

```php
use WpPack\Component\Setting\Attribute\SettingsPageAction;
use WpPack\Component\Setting\SettingsRegistrar;

class PluginSettingsManager
{
    public function __construct(
        private readonly SettingsRegistrar $settings,
    ) {}

    #[SettingsPageAction(page: 'settings_page_my-plugin')]
    public function registerSettings(): void
    {
        $this->settings->register('my_plugin_settings', 'my_plugin_options', [
            'sanitize_callback' => [$this, 'sanitize'],
        ]);

        $this->settings->addSection('general', 'General Settings', 'my_plugin_settings');

        $this->settings->addField('api_key', 'API Key', 'my_plugin_settings', 'general', [
            'type' => 'text',
            'label_for' => 'api_key',
        ]);
    }
}
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
