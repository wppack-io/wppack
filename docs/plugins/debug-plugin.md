# DebugPlugin

WordPress の開発デバッグツールバーとプロファイラーを有効にするプラグイン。`wppack/debug` コンポーネントが提供するデータ収集・ツールバー描画・プロファイリング機能を、WordPress プラグインとしてブートストラップする薄いレイヤー。

## 概要

DebugPlugin は `wppack/debug` の薄いラッパーです:

- **データ収集**: `wppack/debug` の DataCollector 群がリクエスト・イベント・コンテナ等の情報を収集
- **ツールバー描画**: `wppack/debug` の `ToolbarRenderer` / `ToolbarSubscriber` がページ下部にツールバーを出力
- **プロファイリング**: `wppack/debug` の `Profiler` / `Profile` が実行時間を計測
- **エラーハンドラ**: `ExceptionHandler` / `WpDieHandler` がエラーを整形表示
- **リダイレクトハンドラ**: `RedirectHandler` が POST → redirect → GET フローでツールバー付き中間ページを表示
- **致命的エラーハンドラ**: `FatalErrorHandler` が `fatal-error-handler.php` ドロップイン経由で致命的エラーを整形表示
- **早期例外ハンドラ**: `ExceptionHandler`（軽量モード）が同ドロップイン経由で DI コンテナ起動前の未キャッチ例外を整形表示
- **早期リダイレクトハンドラ**: `RedirectHandler`（軽量モード）が同ドロップイン経由で DI コンテナ起動前のリダイレクトもインターセプト
- **DebugPlugin** はプラグインブートストラップ、`DebugConfig` のオーバーライド（`enabled: true`, `showToolbar: true`）、コンパイラーパス登録、ドロップイン管理を担当

## アーキテクチャ

### パッケージ構成

```
wppack/debug            ← デバッグ基盤（DataCollector, Profiler, Toolbar, ErrorHandler, FatalErrorHandler, ExceptionHandler）
    ↑
wppack/logger           ← PSR-3 ロガー（エラーハンドラのログ出力）
    ↑
wppack/debug-plugin     ← WordPress 統合（ブートストラップ, 設定, DI, ドロップイン管理）
```

### レイヤー構成

```
src/Plugin/DebugPlugin/
├── wppack-debug.php                              ← Bootstrap（Kernel::registerPlugin）
├── src/
│   ├── DebugPlugin.php                           ← PluginInterface 実装
│   └── DependencyInjection/
│       └── DebugPluginServiceProvider.php         ← サービス登録
└── tests/
```

## 依存パッケージ

| パッケージ | 用途 |
|-----------|------|
| wppack/debug | デバッグ基盤（DataCollector, Profiler, Toolbar, ErrorHandler） |
| wppack/dependency-injection | DI コンテナ |
| wppack/kernel | プラグインブートストラップ（PluginInterface） |
| wppack/hook | WordPress フック統合 |
| wppack/logger | PSR-3 ロガー（エラーハンドラのログ出力） |
| wppack/stopwatch | コード実行時間の計測 |
| wppack/templating | テンプレートエンジン（パネルレンダリング） |

## 名前空間

```
WpPack\Plugin\DebugPlugin\
```

## 設定

`wp-config.php` で `WP_DEBUG` を `true` に設定してプラグインを有効化するだけで動作します。追加の設定は不要です。

```php
// wp-config.php
define('WP_DEBUG', true);
```

### Fatal Error Handler ドロップイン

プラグイン有効化時に `fatal-error-handler.php` を `wp-content/` にコピーします。このドロップインは 3 つのサービスを早期登録します:

1. **`ExceptionHandler`** — ドロップインロード時に `set_exception_handler()` で登録（DI 依存なしの軽量モード）。プラグインロード中、DI コンテナコンパイル中、`Kernel::boot()` 内部等で発生する未キャッチ例外をキャッチ。`DebugPlugin::boot()` 後は DI 依存注入済みの `ExceptionHandler` が上書きし、早期インスタンスは previous handler として保持される
2. **`RedirectHandler`** — `wp_redirect` フィルタでリダイレクトをインターセプト（DI 依存なしの軽量モード、ツールバーなし）。`DebugPlugin::boot()` 後は DI 依存注入済みの `RedirectHandler` が上書き
3. **`FatalErrorHandler`** — `WP_Fatal_Error_Handler` 実装として return。致命的な PHP エラー（`E_ERROR`, `E_PARSE` 等）をシャットダウン時にキャッチ

両方とも `ErrorRenderer` による詳細なエラーページを表示します。

プラグイン無効化時には、WpPack が設置したドロップインのみ削除します（シグネチャで判定）。

### `WPPACK_DEBUG_ENABLED` キルスイッチ

サーバーレス環境等でドロップインファイルを削除できない場合は、`WPPACK_DEBUG_ENABLED` 定数で無効化できます:

```php
// wp-config.php
define('WPPACK_DEBUG_ENABLED', false);
```

| 状態 | 動作 |
|------|------|
| 未定義 + `WP_DEBUG=true` | 有効（カスタムハンドラ） |
| 未定義 + `WP_DEBUG=false` | 無効（WordPress デフォルト） |
| `= false` | 強制無効（ファイル削除不要のキルスイッチ） |
| `= true` + `WP_DEBUG=true` | 有効 |
| `= true` + `WP_DEBUG=false` | 無効（`WP_DEBUG` が優先） |

### セーフティガード

以下の条件ではプラグインがロードされません:

- `WP_DEBUG` が `false` または未定義
- `wp_get_environment_type()` が `'production'` を返す
- リクエスト元 IP がホワイトリスト外
- 現在のユーザーが許可されたロールを持っていない

ツールバーの表示条件（IP・ロール）は `DebugConfig` で制御されます。デフォルトでは localhost（`127.0.0.1` / `::1`）からの管理者アクセスのみ表示されます。

## 主要クラス

### DebugPlugin

`PluginInterface` 実装。`Kernel::registerPlugin()` で登録される。

```php
namespace WpPack\Plugin\DebugPlugin;

final class DebugPlugin extends AbstractPlugin
{
    public function register(ContainerBuilder $builder): void;
    public function getCompilerPasses(): array;
    public function boot(Container $container): void;
    public function onActivate(): void;   // fatal-error-handler.php drop-in install
    public function onDeactivate(): void; // fatal-error-handler.php drop-in removal
}
```

#### コンパイラーパス

`getCompilerPasses()` は以下のパスを登録します:

| パス | 説明 |
|-----|------|
| `RegisterLoggerPass` | ロガーチャンネルの登録 |
| `RegisterDataCollectorsPass` | `#[AsDataCollector]` アトリビュート付きクラスの自動検出・登録 |
| `RegisterPanelRenderersPass` | `#[AsPanelRenderer]` アトリビュート付きクラスの自動検出・登録 |
| `RegisterHookSubscribersPass` | `hook.subscriber` タグ付きサービスの登録 |
| `InjectContainerSnapshotPass` | コンパイル時のコンテナ状態を `ContainerDataCollector` に注入 |

#### boot()

`boot()` で以下のサービスを起動します:

- `ToolbarSubscriber::register()` — `wp_footer` / `admin_footer` フックでツールバーを出力
- `RedirectHandler::register()` — `wp_redirect` フィルタでリダイレクトをインターセプトし中間ページを表示
- `ExceptionHandler::register()` — `set_exception_handler()` で例外を整形表示
- `WpDieHandler::register()` — `wp_die_handler` フィルタでエラーを整形表示

#### onActivate() / onDeactivate()

`fatal-error-handler.php` ドロップインのライフサイクル管理:

- **`onActivate()`**: `wp-content/fatal-error-handler.php` が未存在かつ書き込み可能な場合、`wppack/debug` の `drop-in/fatal-error-handler.php` をコピー
- **`onDeactivate()`**: シグネチャ（`WpPack Fatal Error Handler Drop-in`）を確認し、WpPack 製のドロップインのみ削除

### DependencyInjection\DebugPluginServiceProvider

DI サービスプロバイダ。

```php
namespace WpPack\Plugin\DebugPlugin\DependencyInjection;

final class DebugPluginServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void;
}
```

主な処理:

1. `LoggerServiceProvider` を登録（未登録の場合）
2. `DebugServiceProvider` を登録（DataCollector 群、Profiler、Toolbar、ErrorHandler）
3. `logger.debug` チャンネルを登録（未登録の場合）
4. `DebugConfig` を `enabled: true`, `showToolbar: true` でオーバーライド

## データ収集パネル

`wppack/debug` が提供する標準 DataCollector:

| パネル | 説明 |
|-------|------|
| `request` | リクエスト/レスポンス情報（メソッド、URL、ヘッダー、パラメータ） |
| `event` | WordPress フックの発火状況と実行時間 |
| `container` | DI コンテナのサービス一覧、コンパイラーパス、タグ |
| `http_client` | HTTP API 呼び出しの記録（URL、ステータス、時間） |
| `wordpress` | WordPress バージョン、環境、PHP 情報 |
| `environment` | メモリ使用量、実行時間 |
| `debug_bar_panel` | Debug Bar プラグイン互換パネル（有効時のみ表示） |

### カスタム DataCollector の追加

`#[AsDataCollector]` アトリビュートを付けたクラスを作成するだけで自動検出されます:

```php
use WpPack\Component\Debug\Attribute\AsDataCollector;
use WpPack\Component\Debug\DataCollector\AbstractDataCollector;

#[AsDataCollector(name: 'my_collector', priority: 50)]
final class MyDataCollector extends AbstractDataCollector
{
    public function getName(): string { return 'my_collector'; }
    public function getLabel(): string { return 'My Data'; }

    public function collect(): void
    {
        $this->data = ['key' => 'value'];
    }

    public function getIndicatorValue(): string { return '42'; }
}
```
