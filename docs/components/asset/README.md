# Asset コンポーネント

**パッケージ:** `wppack/asset`
**名前空間:** `WpPack\Component\Asset\`
**レイヤー:** Feature

WordPress のスクリプト／スタイル API（`wp_enqueue_script()` / `wp_enqueue_style()` 等）をオブジェクト指向でラップするコンポーネントです。DI 可能なアセット管理を提供します。

## インストール

```bash
composer require wppack/asset
```

## 基本コンセプト

### Before（従来の WordPress）

```php
// グローバル関数の直接呼び出し — テスト・DI が困難
wp_enqueue_script('my-script', plugins_url('js/app.js', __FILE__), ['jquery'], '1.0.0', true);
wp_add_inline_script('my-script', 'var config = {};', 'before');
wp_localize_script('my-script', 'myData', ['ajaxUrl' => admin_url('admin-ajax.php')]);

if (wp_script_is('jquery', 'enqueued')) {
    // ...
}

wp_enqueue_style('my-style', plugins_url('css/app.css', __FILE__), [], '1.0.0');
wp_add_inline_style('my-style', '.custom { color: red; }');
```

### After（WpPack）

```php
use WpPack\Component\Asset\AssetManager;

class AdminAssetSubscriber
{
    public function __construct(
        private readonly AssetManager $asset,
    ) {}

    public function enqueueScripts(): void
    {
        $this->asset->enqueueScript('my-script', plugins_url('js/app.js', __FILE__), ['jquery'], '1.0.0', true);
        $this->asset->addInlineScript('my-script', 'var config = {};', 'before');
        $this->asset->localizeScript('my-script', 'myData', ['ajaxUrl' => admin_url('admin-ajax.php')]);

        if ($this->asset->scriptIs('jquery', 'enqueued')) {
            // ...
        }

        $this->asset->enqueueStyle('my-style', plugins_url('css/app.css', __FILE__), [], '1.0.0');
        $this->asset->addInlineStyle('my-style', '.custom { color: red; }');
    }
}
```

## AssetManager

WordPress のスクリプト／スタイル関数を型安全にラップするサービスクラスです。

### スクリプト メソッド一覧

| メソッド | WordPress API | 説明 |
|---------|--------------|------|
| `registerScript(handle, src, deps, ver, args): bool` | `wp_register_script()` | スクリプトを登録 |
| `enqueueScript(handle, src, deps, ver, args): void` | `wp_enqueue_script()` | スクリプトをエンキュー |
| `dequeueScript(handle): void` | `wp_dequeue_script()` | スクリプトをデキュー |
| `deregisterScript(handle): void` | `wp_deregister_script()` | スクリプト登録を解除 |
| `scriptIs(handle, status): bool` | `wp_script_is()` | スクリプトのステータスを確認 |
| `addInlineScript(handle, data, position): bool` | `wp_add_inline_script()` | インラインスクリプトを追加 |
| `localizeScript(handle, objectName, l10n): bool` | `wp_localize_script()` | スクリプトにデータを渡す |

### スタイル メソッド一覧

| メソッド | WordPress API | 説明 |
|---------|--------------|------|
| `registerStyle(handle, src, deps, ver, media): bool` | `wp_register_style()` | スタイルを登録 |
| `enqueueStyle(handle, src, deps, ver, media): void` | `wp_enqueue_style()` | スタイルをエンキュー |
| `dequeueStyle(handle): void` | `wp_dequeue_style()` | スタイルをデキュー |
| `deregisterStyle(handle): void` | `wp_deregister_style()` | スタイル登録を解除 |
| `styleIs(handle, status): bool` | `wp_style_is()` | スタイルのステータスを確認 |
| `addInlineStyle(handle, data): bool` | `wp_add_inline_style()` | インラインスタイルを追加 |

## クイックリファレンス

### スクリプト

```php
$asset->registerScript('handle', '/js/app.js');                     // スクリプトを登録
$asset->enqueueScript('handle', '/js/app.js', ['jquery'], '1.0');   // エンキュー
$asset->dequeueScript('handle');                                     // デキュー
$asset->deregisterScript('handle');                                  // 登録解除
$asset->scriptIs('handle', 'enqueued');                              // ステータス確認
$asset->addInlineScript('handle', 'var x = 1;', 'before');          // インライン追加
$asset->localizeScript('handle', 'obj', ['key' => 'val']);          // データ渡し
```

### スタイル

```php
$asset->registerStyle('handle', '/css/app.css');                     // スタイルを登録
$asset->enqueueStyle('handle', '/css/app.css', [], '1.0');          // エンキュー
$asset->dequeueStyle('handle');                                      // デキュー
$asset->deregisterStyle('handle');                                   // 登録解除
$asset->styleIs('handle', 'enqueued');                               // ステータス確認
$asset->addInlineStyle('handle', '.foo { color: red; }');           // インライン追加
```

## 利用シーン

**最適なケース:**
- プラグイン・テーマでのスクリプト/スタイルのエンキュー
- DI コンテナ経由でアセット管理をテスタブルにしたい場合
- 管理画面カスタマイズでの条件付きアセット読み込み

**代替を検討すべきケース:**
- `$wp_scripts`/`$wp_styles` グローバルの読み取り専用イントロスペクション（Debug コンポーネントを使用）

## Named Hook アトリビュート

Asset コンポーネントは Hook コンポーネントの以下のアトリビュートと組み合わせて使用します:

```php
use WpPack\Component\Asset\AssetManager;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminEnqueueScriptsAction;
use WpPack\Component\Hook\Attribute\Theme\Action\WpEnqueueScriptsAction;

final class AssetSubscriber
{
    public function __construct(
        private readonly AssetManager $asset,
    ) {}

    #[WpEnqueueScriptsAction]
    public function enqueueFrontendAssets(): void
    {
        $this->asset->enqueueStyle('my-theme', get_stylesheet_uri());
    }

    #[AdminEnqueueScriptsAction]
    public function enqueueAdminAssets(): void
    {
        $this->asset->enqueueScript('my-admin', '/js/admin.js', [], '1.0.0', true);
    }
}
```

→ [Hook コンポーネントのドキュメント](../hook/) も参照してください。

## 依存関係

### 必須
なし — WordPress のスクリプト/スタイル関数をそのまま利用

### 推奨
- **Hook コンポーネント** — Attribute ベースの `admin_enqueue_scripts` / `wp_enqueue_scripts` フック登録
