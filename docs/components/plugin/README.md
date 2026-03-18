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

## Named Hook アトリビュート

→ [Hook コンポーネントのドキュメント](../hook/plugin.md) を参照してください。
## Hook アトリビュートリファレンス

```php
// プラグイン管理
#[ActivatedPluginAction(priority?: int = 10)]        // プラグイン有効化後
#[DeactivatedPluginAction(priority?: int = 10)]      // プラグイン無効化後
#[PluginActionLinksFilter(plugin: string, priority?: int = 10)]           // プラグインアクションリンク
#[NetworkPluginActionLinksFilter(plugin: string, priority?: int = 10)]    // ネットワーク管理アクションリンク
#[PluginRowMetaFilter(priority?: int = 10)]          // プラグイン行メタリンク
#[AfterPluginRowAction(priority?: int = 10)]         // プラグイン一覧の行の後

// プラグイン更新
#[UpgraderProcessCompleteAction(priority?: int = 10)]                     // 更新完了後
#[PreSetSiteTransientUpdatePluginsFilter(priority?: int = 10)]            // 更新情報の変更
#[PluginsApiFilter(priority?: int = 10)]                                  // プラグイン API 結果のフィルタリング

// プラグイン読み込み
#[PluginLoadedAction(priority?: int = 10)]           // 個別プラグイン読み込み完了
#[NetworkPluginsLoadedAction(priority?: int = 10)]   // ネットワークプラグイン読み込み完了
#[MuPluginsLoadedAction(priority?: int = 10)]        // Must-Use プラグイン読み込み完了
```

> [!NOTE]
> `plugins_loaded` フックは Hook コンポーネントの [`PluginsLoadedAction`](../hook/README.md) を使用してください。

## このコンポーネントの使用場面

**最適な用途：**
- プラグイン管理画面の拡張（アクションリンク、行メタ）を宣言的に行いたい場合
- 他プラグインの有効化/無効化/更新イベントに反応したい場合
- `plugin_action_links`、`plugin_row_meta`、`upgrader_process_complete` などのフックを型安全に扱いたい場合

**代替を検討すべき場合：**
- プラグイン自身の初期化・有効化・無効化 → `PluginInterface`（[Kernel コンポーネント](../kernel/README.md)）を使用

## 依存関係

### 必須
- **Hook コンポーネント** - WordPress フック登録用
