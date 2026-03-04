# Setting コンポーネント

**パッケージ:** `wppack/setting`
**名前空間:** `WpPack\Component\Setting\`
**レイヤー:** Application

WordPress Settings API をモダンな PHP で扱うためのコンポーネントです。クラス定義だけで設定ページ・セクション・フィールドが自動登録されます。

## インストール

```bash
composer require wppack/setting
```

## 主要クラス

| クラス | 説明 |
|--------|------|
| `AsSettingsPage` | 設定ページを定義するクラスレベルアトリビュート |
| `AbstractSettingsPage` | 設定ページの基底クラス |
| `SettingsConfigurator` | セクション・フィールドを定義するビルダー |
| `SectionDefinition` | セクション定義（フルーエント API） |
| `FieldDefinition` | フィールド定義（値オブジェクト） |
| `SettingsRegistry` | 設定ページの自動登録レジストリ |

## 基本コンセプト

### Before（従来の WordPress）

```php
add_action('admin_menu', function () {
    add_options_page('My Plugin', 'My Plugin', 'manage_options', 'my-plugin', 'render_page');
});
add_action('admin_init', function () {
    register_setting('my_plugin_settings', 'my_plugin_options');
    add_settings_section('general', 'General Settings', null, 'my-plugin');
    add_settings_field('api_key', 'API Key', 'api_key_callback', 'my-plugin', 'general');
});
// フォーム、バリデーション、保存のために50行以上の手続き型コードが必要...
```

### After（WpPack）

```php
use WpPack\Component\OptionsResolver\OptionsResolver;
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;

#[AsSettingsPage(
    slug: 'my-plugin',
    title: 'My Plugin Settings',
    menuTitle: 'My Plugin',
    optionName: 'my_plugin_options',
)]
class MyPluginSettings extends AbstractSettingsPage
{
    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('general', __('General', 'my-plugin'))
            ->field('api_key', __('API Key', 'my-plugin'), $this->renderApiKey(...))
            ->field('debug', __('Debug Mode', 'my-plugin'), $this->renderDebug(...));

        $settings->section('advanced', __('Advanced', 'my-plugin'))
            ->field('cache_ttl', __('Cache TTL', 'my-plugin'), $this->renderCacheTtl(...));
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('api_key', '');
        $resolver->setAllowedTypes('api_key', 'string');

        $resolver->setDefault('debug', false);
        $resolver->setAllowedTypes('debug', 'bool');
    }

    private function renderApiKey(array $args): void
    {
        printf(
            '<input type="text" id="api_key" name="%s[api_key]" value="%s" class="regular-text" />',
            esc_attr($this->optionName),
            esc_attr($this->getOption('api_key', '')),
        );
    }

    private function renderDebug(array $args): void { /* ... */ }
    private function renderCacheTtl(array $args): void { /* ... */ }
}
```

## 使い方

### AsSettingsPage アトリビュート

クラスレベルのアトリビュートで設定ページのメタデータを定義します。

```php
#[AsSettingsPage(
    slug: 'my-plugin',            // ページスラグ（必須）
    title: 'My Plugin Settings',  // ページタイトル（必須）
    menuTitle: 'My Plugin',       // メニュー表示名（デフォルト: title と同じ）
    capability: 'manage_options', // 必要な権限（デフォルト: manage_options）
    optionName: 'my_plugin_opts', // オプション名（デフォルト: slug のハイフン→アンダースコア変換）
    optionGroup: 'my_plugin_grp', // オプショングループ（デフォルト: optionName と同じ）
    parent: 'options-general.php',// 親メニュー（null でトップレベル、デフォルト: options-general.php）
    icon: null,                   // トップレベルメニュー用アイコン
    position: null,               // メニュー位置
)]
```

### AbstractSettingsPage

設定ページの基底クラスです。`configure()` メソッドでセクション・フィールドを定義します。

#### 必須メソッド

- `configure(SettingsConfigurator $settings): void` — セクション・フィールド定義

#### オプションメソッド

- `configureOptions(OptionsResolver $resolver): void` — OptionsResolver による型バリデーション・デフォルト値
- `sanitize(array $input): array` — カスタムサニタイズロジック
- `render(): void` — ページレンダリング（デフォルト実装あり）

#### ヘルパーメソッド

- `getOption(string $key, mixed $default = null): mixed` — 保存されたオプション値の取得

### SettingsConfigurator

フルーエント API でセクション・フィールドを定義します。

```php
protected function configure(SettingsConfigurator $settings): void
{
    $settings->section('general', 'General Settings')
        ->field('field_1', 'Field 1', $this->renderField1(...))
        ->field('field_2', 'Field 2', $this->renderField2(...), ['label_for' => 'field_2']);

    $settings->section('advanced', 'Advanced', fn () => echo '<p>Description</p>')
        ->field('field_3', 'Field 3', $this->renderField3(...));
}
```

### SettingsRegistry

設定ページを WordPress に自動登録します。`admin_menu` と `admin_init` フックに自動的にバインドします。

```php
$registry = new SettingsRegistry();
$registry->register(new MyPluginSettings());
// admin_menu → addMenuPage() が自動呼び出し
// admin_init → initSettings() が自動呼び出し
```

### OptionsResolver 統合

`configureOptions()` をオーバーライドすると、保存時に OptionsResolver による型バリデーションとデフォルト値の適用が自動的に行われます。

```php
protected function configureOptions(OptionsResolver $resolver): void
{
    $resolver->setDefault('api_key', '');
    $resolver->setAllowedTypes('api_key', 'string');

    $resolver->setDefault('debug', false);
    $resolver->setAllowedTypes('debug', 'bool');

    $resolver->setDefault('cache_ttl', 3600);
    $resolver->setAllowedTypes('cache_ttl', 'int');
}
```

### サニタイズの仕組み

| configureOptions() | sanitize() | 動作 |
|:---:|:---:|:---|
| なし | なし | WordPress デフォルト（sanitize_callback なし） |
| あり | なし | OptionsResolver でバリデーション + 型キャスト |
| なし | あり | カスタムサニタイズのみ |
| あり | あり | OptionsResolver → sanitize() の順で適用 |

### トップレベルメニュー

`parent: null` でトップレベルメニューとして登録できます。

```php
#[AsSettingsPage(
    slug: 'my-plugin',
    title: 'My Plugin',
    parent: null,
    icon: 'dashicons-admin-generic',
    position: 80,
)]
class MyPluginSettings extends AbstractSettingsPage { /* ... */ }
```

## Named Hook アトリビュート

### #[SettingsPageAction(page: string, priority?: int)]

**WordPress フック:** `load-{$page}`

設定ページの読み込み時に実行されるアクション。

```php
use WpPack\Component\Setting\Attribute\Action\SettingsPageAction;

class SettingsPageManager
{
    #[SettingsPageAction(page: 'settings_page_my-plugin')]
    public function onSettingsPageLoad(): void
    {
        // ヘルプタブの追加、スクリーンオプションの設定など
    }
}
```

### #[SettingsErrorsAction(priority?: int)]

**WordPress フック:** `settings_errors`

設定エラー表示時に実行されるアクション。

## WordPress 統合

- 設定は通常の WordPress 管理画面ページとして表示
- **WordPress のユーザーロールと権限**と互換性あり
- **マルチサイトネットワーク**でのネットワーク全体の設定に対応
- **WordPress Settings API**との互換性を維持
- **WordPress 管理画面のカラースキーム**とレスポンシブデザインに対応

## 依存関係

### 必須
- **OptionsResolver コンポーネント** - `configureOptions()` による型バリデーション

### 推奨
- **Hook コンポーネント** - Named Hook アトリビュートの利用
- **Option コンポーネント** - 拡張された設定ストレージ
