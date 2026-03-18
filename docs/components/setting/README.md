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
| `SectionDefinition` | セクション定義（フルーエント API + 組み込みフィールドタイプ） |
| `FieldDefinition` | フィールド定義（値オブジェクト） |
| `SettingsRenderer` | ページ・フィールドテンプレートの統合レンダラー |
| `ValidationContext` | バリデーションエラー・警告・情報の通知 |
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
use WpPack\Component\Setting\AbstractSettingsPage;
use WpPack\Component\Setting\Attribute\AsSettingsPage;
use WpPack\Component\Setting\SettingsConfigurator;
use WpPack\Component\Setting\ValidationContext;

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
            ->text('api_key', __('API Key', 'my-plugin'), placeholder: 'Enter your key')
            ->checkbox('debug', __('Debug Mode', 'my-plugin'), label: 'Enable debug mode')
            ->number('cache_ttl', __('Cache TTL', 'my-plugin'), min: 0, step: 60, description: 'Seconds');
    }

    protected function sanitize(array $input): array
    {
        $input['api_key'] = trim((string) ($input['api_key'] ?? ''));
        $input['debug'] = !empty($input['debug']);
        $input['cache_ttl'] = absint($input['cache_ttl'] ?? 0);

        return $input;
    }

    protected function validate(array $input, ValidationContext $context): array
    {
        if ($input['api_key'] === '') {
            $context->error('api_key_required', __('API Key is required.', 'my-plugin'));
            $input['api_key'] = $context->oldValue('api_key', '');
        }

        if ($input['cache_ttl'] < 60) {
            $context->warning('cache_ttl_min', __('Cache TTL must be at least 60 seconds.', 'my-plugin'));
            $input['cache_ttl'] = 60;
        }

        return $input;
    }
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

- `sanitize(array $input): array` — サニタイズ（型変換・正規化）
- `validate(array $input, ValidationContext $context): array` — バリデーション（エラー通知）
- `render(): void` — ページレンダリング（`SettingsRenderer` に委譲）
- `createRenderer(): SettingsRenderer` — レンダラーの生成（サブクラスでオーバーライド可能）

#### ヘルパーメソッド

- `getOption(string $key, mixed $default = null): mixed` — 保存されたオプション値の取得
- `getRenderer(): SettingsRenderer` — レンダラーインスタンスの取得（遅延初期化）

### SettingsConfigurator

フルーエント API でセクション・フィールドを定義します。組み込みフィールドタイプを使うと HTML を手書きする必要がありません。

```php
protected function configure(SettingsConfigurator $settings): void
{
    $settings->section('general', 'General Settings')
        ->text('api_key', 'API Key', placeholder: 'Enter your key')
        ->checkbox('debug', 'Debug Mode', label: 'Enable debug mode')
        ->number('cache_ttl', 'Cache TTL', min: 0, step: 60, description: 'Seconds')
        ->select('log_level', 'Log Level', choices: ['debug' => 'Debug', 'info' => 'Info']);

    $settings->section('advanced', 'Advanced', fn () => echo '<p>Description</p>')
        ->textarea('bio', 'Bio', rows: 5)
        ->field('special', 'Special', fn(array $args) => printf('<div>Custom</div>'));
}
```

### 組み込みフィールドタイプ

`SectionDefinition` には 9 種類の組み込みフィールドタイプメソッドがあります。

| メソッド | デフォルト CSS | label_for |
|---------|-------------|-----------|
| `text()` | `regular-text` | Yes |
| `password()` | `regular-text` | Yes |
| `url()` | `regular-text code` | Yes |
| `email()` | `regular-text` | Yes |
| `number()` | `small-text` | Yes |
| `textarea()` | `large-text` | Yes |
| `checkbox()` | — | No |
| `select()` | — | Yes |
| `radio()` | — | No |

各メソッドは `SettingsRenderer` の同名メソッドに委譲してHTMLを出力します。

#### field() メソッド

`field()` は `\Closure` と `string` の両方を受け付けます。

```php
$settings->section('general', 'General')
    // クロージャ → そのまま使用（レンダラーを通さない）
    ->field('special', 'Special', fn(array $args) => printf('Custom HTML'))
    // 文字列 → レンダラーの同名メソッドに委譲
    ->field('content', 'Content', 'wysiwyg')    // → renderer->wysiwyg(context)
    ->field('color', 'Theme Color', 'color');    // → renderer->color(context)
```

### 2段階サニタイズ/バリデーション パイプライン

フォーム送信時、以下の2段階で入力値が処理されます。

```
フォーム送信 → sanitizeCallback()
  ├─ 1. sanitize（sanitize() オーバーライド / 型変換・正規化）
  └─ 2. validate（validate() + ValidationContext / エラー通知）
```

#### 1. sanitize

`sanitize()` メソッドをオーバーライドすると、入力値の型変換・正規化を実行できます。

```php
protected function sanitize(array $input): array
{
    $input['api_key'] = trim((string) ($input['api_key'] ?? ''));
    $input['debug'] = !empty($input['debug']);
    $input['cache_ttl'] = absint($input['cache_ttl'] ?? 0);

    return $input;
}
```

#### 2. validate

`validate()` メソッドをオーバーライドすると、バリデーションとエラー通知を行えます。

```php
protected function validate(array $input, ValidationContext $context): array
{
    if ($input['api_key'] === '') {
        $context->error('api_key_required', __('API Key is required.', 'my-plugin'));
        $input['api_key'] = $context->oldValue('api_key', '');
    }

    return $input;
}
```

### ValidationContext

`validate()` メソッドで受け取るコンテキストオブジェクトです。WordPress の `add_settings_error()` をラップし、以前の値へのアクセスを提供します。

| メソッド | 説明 |
|--------|------|
| `error(string $code, string $message): void` | エラー通知を追加 |
| `warning(string $code, string $message): void` | 警告通知を追加 |
| `info(string $code, string $message): void` | 情報通知を追加 |
| `oldValue(string $key, mixed $default = null): mixed` | 保存済みの値を取得 |

### パイプライン動作表

| sanitize() | validate() | 動作 |
|:---:|:---:|:---|
| なし | なし | WordPress デフォルト（sanitize_callback なし） |
| あり | なし | sanitize のみ |
| なし | あり | validate のみ |
| あり | あり | sanitize → validate の順で適用 |

### SettingsRegistry

設定ページを WordPress に自動登録します。`admin_menu` と `admin_init` フックに自動的にバインドします。

```php
$registry = new SettingsRegistry();
$registry->register(new MyPluginSettings());
// admin_menu → addMenuPage() が自動呼び出し
// admin_init → initSettings() が自動呼び出し
```

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

### SettingsRenderer

ページとフィールドのテンプレートを一つのクラスに集約するレンダラーです。`AbstractSettingsPage::render()` は `SettingsRenderer::renderPage()` に委譲します。

デフォルトの `SettingsRenderer` は WordPress Admin 標準のHTMLを出力します。サブクラス化してプラグインごとにカスタマイズできます。

```php
use WpPack\Component\Setting\SettingsRenderer;

class MyPluginRenderer extends SettingsRenderer
{
    // ページヘッダーにタブを追加
    public function renderHeader(AbstractSettingsPage $page): void
    {
        parent::renderHeader($page);
        echo '<nav class="nav-tab-wrapper">';
        echo '<a class="nav-tab nav-tab-active">General</a>';
        echo '<a class="nav-tab">Advanced</a>';
        echo '</nav>';
    }

    // テキストフィールドのテンプレート変更
    public function text(array $context): void
    {
        printf(
            '<div class="my-field"><input type="text" id="%s" name="%s" value="%s" /></div>',
            esc_attr($context['id']),
            esc_attr($context['name']),
            esc_attr($context['value']),
        );
    }

    // カスタムフィールドタイプを同名メソッドとして定義
    public function wysiwyg(array $context): void
    {
        wp_editor($context['value'], $context['id'], [
            'textarea_name' => $context['name'],
        ]);
    }
}
```

設定ページで `createRenderer()` をオーバーライドしてカスタムレンダラーを使用します。

```php
#[AsSettingsPage(slug: 'my-plugin', title: 'My Plugin')]
class MyPluginSettings extends AbstractSettingsPage
{
    protected function createRenderer(): SettingsRenderer
    {
        return new MyPluginRenderer();
    }

    protected function configure(SettingsConfigurator $settings): void
    {
        $settings->section('general', 'General')
            ->text('api_key', 'API Key')               // MyPluginRenderer::text()
            ->checkbox('debug', 'Debug Mode')            // SettingsRenderer::checkbox()（デフォルト）
            ->field('content', 'Content', 'wysiwyg');    // MyPluginRenderer::wysiwyg()
    }
}
```

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/setting.md) を参照してください。
## WordPress 統合

- 設定は通常の WordPress 管理画面ページとして表示
- **WordPress のユーザーロールと権限**と互換性あり
- **マルチサイトネットワーク**でのネットワーク全体の設定に対応
- **WordPress Settings API**との互換性を維持
- **WordPress 管理画面のカラースキーム**とレスポンシブデザインに対応

## プラグイン / テーマでの配置

プラグインやテーマで設定ページクラスを作成する場合、以下のディレクトリ構成を推奨します。

```
src/
└── Setting/
    └── Page/
        ├── GeneralSettings.php
        ├── AdvancedSettings.php
        └── DisplaySettings.php
```

> 詳細は[プラグイン開発ガイド](../../guides/plugin-development.md)、[テーマ開発ガイド](../../guides/theme-development.md)を参照してください。

## 依存関係

### 必須
- なし

### 推奨
- **Hook コンポーネント** - Named Hook アトリビュートの利用
- **Option コンポーネント** - 拡張された設定ストレージ
