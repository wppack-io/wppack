# Kernel コンポーネント

**パッケージ:** `wppack/kernel`
**名前空間:** `WpPack\Component\Kernel\`
**レイヤー:** Infrastructure

WordPress アプリケーション全体で1つの DI コンテナのライフサイクル（登録 → コンパイル → ブート）を管理するコンポーネントです。各プラグインは `PluginInterface`、テーマは `ThemeInterface` として Kernel に登録し、`boot()` を呼ぶことでコンテナの構築からサービスの初期化までを一括で行います。

## インストール

```bash
composer require wppack/kernel
```

## 基本コンセプト

### なぜ Kernel が必要か

WordPress では複数のプラグインやテーマがそれぞれ独立して動作します。しかし、DI コンテナを使う場合は、すべてのサービス登録が完了した後にコンテナをコンパイルし、その後に初期化処理を行うという一定の順序が必要です。

Kernel はこの順序を管理し、以下のフェーズを正しい順序で実行します：

1. **登録フェーズ** — 全プラグイン/テーマのサービスを ContainerBuilder に登録
2. **コンパイルフェーズ** — コンパイラーパスを適用し、コンテナを確定
3. **ブートフェーズ** — 確定したコンテナを使って初期化処理を実行

### WordPress フック実行順との対応

```
WordPress コア読み込み         $wpdb 等が利用可能
MU プラグイン読み込み       ─┐
各プラグインファイル実行     │ 登録フェーズ
plugins_loaded              │ Kernel 初期化 & Plugin/Theme 登録
テーマ functions.php 実行    │
after_setup_theme          ─┘
init                        ← compile() → Container 確定
wp_loaded 以降               ブートフェーズ（boot 実行）
```

## 使い方

### 基本的な使い方

各プラグイン/テーマのメインファイルでワンライナーで Kernel に登録します。`init` フック（priority 0）で自動的に `boot()` が呼ばれます：

```php
// my-plugin.php
use WpPack\Component\Kernel\Kernel;

Kernel::registerPlugin(new MyPlugin(), __FILE__);
```

```php
// functions.php
use WpPack\Component\Kernel\Kernel;

Kernel::registerTheme(new MyTheme());
```

`registerPlugin()` は初回呼び出し時に共有の Kernel インスタンスを自動生成し、`init` フックで `boot()` をスケジュールします。第2引数 `$pluginFile` にエントリーポイントの `__FILE__` を渡すことで、`register_activation_hook()` / `register_deactivation_hook()` が自動登録されます。

`registerTheme()` も同様に Kernel インスタンスの自動生成と `boot()` のスケジュールを行います。各プラグイン/テーマは互いの存在を意識する必要がありません。

### コンストラクタオプション

```php
$kernel = new Kernel(
    environment: null,   // null → wp_get_environment_type() で自動取得（未定義時は 'production'）
    debug: null,         // null → WP_DEBUG 定数から自動取得（未定義時は false）
    autoBoot: true,      // true → init (priority 0) で自動 boot()
);
```

`getInstance()` 経由（`registerPlugin` / `registerTheme` 含む）で生成される場合は、すべてデフォルト値（WordPress から自動取得）が使用されます。

### 手動ブート

テストやカスタムブートストラップでは、インスタンスを直接操作できます：

```php
$kernel = new Kernel(debug: true, autoBoot: false);
$kernel->addPlugin(new MyPlugin());
$kernel->addTheme(new MyTheme());

$container = $kernel->boot();
```

### プラグインの実装

`PluginInterface` は `ServiceProviderInterface` を拡張し、コンパイラーパスの提供・ブート処理・アクティベーション/ディアクティベーションのフックを追加します：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ServiceDiscovery;
use WpPack\Component\Kernel\PluginInterface;

final class MyPlugin implements PluginInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $discovery = new ServiceDiscovery($builder);
        $discovery->discover(__DIR__ . '/src', 'MyPlugin\\');
    }

    public function getCompilerPasses(): array
    {
        return [new RegisterHandlersPass()];
    }

    public function boot(Container $container): void
    {
        // Container 確定後の初期化処理
    }

    public function onActivate(): void
    {
        // プラグイン有効化時の処理（テーブル作成等）
    }

    public function onDeactivate(): void
    {
        // プラグイン無効化時の処理
    }
}
```

### テーマの実装

`ThemeInterface` は `ServiceProviderInterface` を拡張し、コンパイラーパスの提供・ブート処理を追加します：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ServiceDiscovery;
use WpPack\Component\Kernel\ThemeInterface;

final class MyTheme implements ThemeInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $discovery = new ServiceDiscovery($builder);
        $discovery->discover(__DIR__ . '/src', 'MyTheme\\');
    }

    public function getCompilerPasses(): array
    {
        return [];
    }

    public function boot(Container $container): void
    {
        // Container 確定後の初期化処理
    }
}
```

## ブートシーケンス

`Kernel::boot()` は以下の順序で処理を実行します：

```
1. 全プラグインの register() を呼ぶ
2. 全テーマの register() を呼ぶ
3. 全プラグインの getCompilerPasses() を収集し addCompilerPass()
4. 全テーマの getCompilerPasses() を収集し addCompilerPass()
5. ContainerBuilder::compile() でコンテナを確定
6. 全プラグインの boot() を呼ぶ
7. 全テーマの boot() を呼ぶ
```

**プラグインが常にテーマより先に登録・ブートされます。** これにより、テーマはプラグインが登録したサービスをオーバーライドしたり拡張したりできます。

## ガード

Kernel は以下の不正な操作を防止します：

### 二重ブート防止

```php
$kernel = new Kernel();
$kernel->boot();
$kernel->boot(); // KernelAlreadyBootedException
```

### ブート後のプラグイン/テーマ追加防止

```php
$kernel = new Kernel();
$kernel->boot();
$kernel->addPlugin(new MyPlugin()); // KernelAlreadyBootedException
$kernel->addTheme(new MyTheme());   // KernelAlreadyBootedException
```

### ブート前のコンテナ取得防止

```php
$kernel = new Kernel();
$kernel->getContainer(); // LogicException
```

## 実践例

WordPress プラグインメインファイルでの完全なワークフロー例：

```php
// my-plugin.php（プラグインメインファイル）
use WpPack\Component\Kernel\Kernel;

Kernel::registerPlugin(new MyPlugin(), __FILE__);
```

`Kernel::registerPlugin()` の第2引数にエントリーポイントの `__FILE__` を渡すだけで、`onActivate()` / `onDeactivate()` が WordPress の有効化・無効化フックに自動登録されます。

```php
// MyPlugin.php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ServiceDiscovery;
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;
use WpPack\Component\Kernel\PluginInterface;

final class MyPlugin implements PluginInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // WordPress サービスの登録
        $builder->addServiceProvider(new WordPressServiceProvider());

        // パラメータの設定
        $builder->setParameter('plugin.dir', __DIR__);
        $builder->setParameter('plugin.url', plugin_dir_url(__FILE__));

        // サービスの自動検出
        $discovery = new ServiceDiscovery($builder);
        $discovery->discover(
            __DIR__ . '/src',
            'MyPlugin\\',
        );
    }

    public function getCompilerPasses(): array
    {
        return [
            new RegisterHooksPass(),
            new RegisterShortcodesPass(),
        ];
    }

    public function boot(Container $container): void
    {
        // Container 確定後の初期化処理
    }

    public function onActivate(): void
    {
        // プラグイン有効化時の処理（テーブル作成等）
    }

    public function onDeactivate(): void
    {
        // プラグイン無効化時の処理
    }
}
```

## 拡張性

`Kernel` クラスは `final` ではないため、キャッシュ付きコンテナの読み込みなど、サブクラスで拡張できます：

```php
class CachedKernel extends Kernel
{
    public function boot(): Container
    {
        $cachePath = '/path/to/cache/container.php';

        if (file_exists($cachePath)) {
            require_once $cachePath;
            // キャッシュ済みコンテナを使用
        }

        return parent::boot();
    }
}
```

## API リファレンス

### Kernel

#### Static メソッド

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `registerPlugin(PluginInterface $plugin, string $pluginFile)` | `void` | 共有インスタンスにプラグインを登録し、有効化・無効化フックを自動登録 |
| `registerTheme(ThemeInterface $theme)` | `void` | 共有インスタンスにテーマを登録 |
| `getInstance()` | `self` | 共有インスタンスを取得（初回呼び出し時に自動生成） |

#### インスタンスメソッド

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `addPlugin(PluginInterface $plugin)` | `self` | プラグインを追加（boot 後は `KernelAlreadyBootedException`） |
| `addTheme(ThemeInterface $theme)` | `self` | テーマを追加（boot 後は `KernelAlreadyBootedException`） |
| `boot()` | `Container` | 登録→コンパイル→ブートを実行（二重呼び出しは `KernelAlreadyBootedException`） |
| `getContainer()` | `Container` | コンパイル済みコンテナを取得（boot 前は `LogicException`） |
| `getEnvironment()` | `string` | 環境名（`production`, `development`, `staging`, `local`） |
| `isDebug()` | `bool` | デバッグモードかどうか |
| `isBooted()` | `bool` | ブート済みかどうか |
| `getPlugins()` | `PluginInterface[]` | 登録済みプラグイン一覧 |
| `getThemes()` | `ThemeInterface[]` | 登録済みテーマ一覧 |

### PluginInterface

`ServiceProviderInterface` を拡張。

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `register(ContainerBuilder $builder)` | `void` | サービスを ContainerBuilder に登録 |
| `getCompilerPasses()` | `CompilerPassInterface[]` | コンパイラーパスを返す |
| `boot(Container $container)` | `void` | コンテナ確定後の初期化処理 |
| `onActivate()` | `void` | プラグイン有効化時の処理 |
| `onDeactivate()` | `void` | プラグイン無効化時の処理 |

### ThemeInterface

`ServiceProviderInterface` を拡張。

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `register(ContainerBuilder $builder)` | `void` | サービスを ContainerBuilder に登録 |
| `getCompilerPasses()` | `CompilerPassInterface[]` | コンパイラーパスを返す |
| `boot(Container $container)` | `void` | コンテナ確定後の初期化処理 |

### KernelAlreadyBootedException

`LogicException` を継承。二重ブートやブート後のプラグイン/テーマ追加時にスローされます。

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Kernel` | アプリケーションカーネル（ライフサイクル管理） |
| `PluginInterface` | プラグイン用インターフェース |
| `ThemeInterface` | テーマ用インターフェース |
| `Exception\KernelAlreadyBootedException` | 二重ブート防止例外 |
