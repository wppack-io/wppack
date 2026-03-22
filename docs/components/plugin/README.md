# Plugin コンポーネント

**パッケージ:** `wppack/plugin`
**名前空間:** `WpPack\Component\Plugin\`
**レイヤー:** Application

WordPress プラグインライフサイクルに関連するフック（`activated_plugin`、`deactivated_plugin`、`plugin_action_links_{plugin}` など）を Named Hook Attributes でラップするコンポーネントです。

> [!NOTE]
> プラグインのブートストラップ、サービスコンテナ、サービスプロバイダーパターンなどのフレームワーク機能は [Kernel コンポーネント](../kernel/README.md) が提供します。

> [!NOTE]
> `plugins_loaded` フックは Hook コンポーネントの `PluginsLoadedAction` を使用してください。Plugin コンポーネントはプラグイン管理・更新・読み込みに特化した Named Hook を提供します。

## インストール

```bash
composer require wppack/plugin
```

## Hook アトリビュート

→ 詳細は [Hook コンポーネント — Plugin](../hook/plugin.md) を参照してください。

## ユーティリティクラス

### PluginPathResolver

プラグインファイルパスからディレクトリパス・URL・ベース名を解決するユーティリティ。`plugin_dir_url()`、`plugin_dir_path()`、`plugin_basename()` のラッパーとして、DI コンテナ経由でのインジェクションを可能にします。

```php
use WpPack\Component\Plugin\PluginPathResolver;

$resolver = new PluginPathResolver(__FILE__);

$resolver->getUrl();      // plugin_dir_url() — https://example.com/wp-content/plugins/my-plugin/
$resolver->getPath();     // plugin_dir_path() — /var/www/html/wp-content/plugins/my-plugin/
$resolver->getBasename(); // plugin_basename() — my-plugin/my-plugin.php
```

**主な利用場面:**
- `AdminAssetSubscriber` 等で `plugins_url()` / `plugin_dir_url()` を直接呼ばずに、DI でプラグインパスを注入したい場合
- テスト時にプラグインファイルパスをモック可能にしたい場合

## このコンポーネントの使用場面

**最適な用途：**
- プラグイン管理画面の拡張（アクションリンク、行メタ）を宣言的に行いたい場合
- 他プラグインの有効化/無効化/更新イベントに反応したい場合
- `plugin_action_links`、`plugin_row_meta`、`upgrader_process_complete` などのフックを型安全に扱いたい場合

**代替を検討すべき場合：**
- プラグイン自身の初期化・有効化・無効化 → `PluginInterface`（[Kernel コンポーネント](../kernel/README.md)）を使用

