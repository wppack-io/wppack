# Kernel コンポーネント

**パッケージ:** `wppack/kernel`
**名前空間:** `WpPack\Component\Kernel\`
**レイヤー:** Infrastructure

Kernel コンポーネントは、WpPack アプリケーションのフレームワークブートストラップレイヤーを提供します。サービスコンテナの初期化、環境対応の設定管理、プラグインとテーマのライフサイクル管理、マルチサイトサポートを行います。

## インストール

```bash
composer require wppack/kernel
```

## 基本コンセプト

### 従来の WordPress vs WpPack

```php
// 従来の WordPress - 散在する初期化
add_action('plugins_loaded', 'my_plugin_init');
function my_plugin_init() {
    require_once __DIR__ . '/includes/class-my-service.php';
    require_once __DIR__ . '/includes/class-my-repository.php';
    // 手動でのワイヤリング...
}

// WpPack Kernel - 構造化されたブートストラップ
use WpPack\Component\Kernel\PluginKernel;

final class MyPluginKernel extends PluginKernel
{
    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->discover(
            namespace: 'MyPlugin\\',
            directory: __DIR__ . '/src',
        );
    }
}
```

## プラグイン Kernel

WordPress プラグインのライフサイクル全体を管理する Kernel を作成します：

```php
use WpPack\Component\Kernel\PluginKernel;
use WpPack\Component\DependencyInjection\ContainerBuilder;

final class MyPluginKernel extends PluginKernel
{
    protected function configureContainer(ContainerBuilder $container): void
    {
        // サービスを自動検出
        $container->discover(
            namespace: 'MyPlugin\\',
            directory: __DIR__ . '/src',
        );

        // 設定を登録
        $container->loadConfig(__DIR__ . '/config/services.php');
    }

    protected function boot(): void
    {
        // プラグイン固有の初期化
        $this->registerPostTypes();
        $this->registerTaxonomies();
    }
}

// プラグインのメインファイルで：
$kernel = new MyPluginKernel(__FILE__);
$kernel->run();
```

### アクティベーションとデアクティベーションフック

```php
final class MyPluginKernel extends PluginKernel
{
    protected function onActivate(): void
    {
        // データベーステーブルを作成
        $this->container->get(DatabaseMigrator::class)->up();

        // デフォルトオプションを設定
        update_option('my_plugin_version', '1.0.0');

        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }

    protected function onDeactivate(): void
    {
        // スケジュールイベントをクリーンアップ
        wp_clear_scheduled_hook('my_plugin_daily_task');

        // リライトルールをフラッシュ
        flush_rewrite_rules();
    }
}
```

## テーマ Kernel

WordPress テーマ用には `ThemeKernel` を使用します：

```php
use WpPack\Component\Kernel\ThemeKernel;

final class MyThemeKernel extends ThemeKernel
{
    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->discover(
            namespace: 'MyTheme\\',
            directory: __DIR__ . '/src',
        );
    }

    protected function boot(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('html5', [
            'search-form', 'comment-form', 'comment-list', 'gallery', 'caption',
        ]);

        register_nav_menus([
            'primary' => 'Primary Navigation',
            'footer' => 'Footer Navigation',
        ]);
    }
}

// functions.php で：
$kernel = new MyThemeKernel(get_template_directory());
$kernel->run();
```

## 環境設定

Kernel は環境固有の設定をサポートします：

```php
final class MyPluginKernel extends PluginKernel
{
    protected function configureContainer(ContainerBuilder $container): void
    {
        // 基本設定を読み込み
        $container->loadConfig(__DIR__ . '/config/services.php');

        // 環境固有の設定を読み込み
        $envConfigFile = __DIR__ . '/config/services_' . $this->getEnvironment() . '.php';
        if (file_exists($envConfigFile)) {
            $container->loadConfig($envConfigFile);
        }
    }

    private function getEnvironment(): string
    {
        return wp_get_environment_type(); // 'local', 'development', 'staging', 'production'
    }
}
```

## サービスディスカバリー

Kernel はコンテナを通じてサービスを自動検出し登録します：

```php
protected function configureContainer(ContainerBuilder $container): void
{
    // アノテーション付きのすべてのサービスを検出
    $container->discover(
        namespace: 'MyPlugin\\',
        directory: __DIR__ . '/src',
    );

    // 以下のアトリビュートが自動検出されます：
    // #[AsService]          - サービス登録
    // #[AsHookSubscriber]   - フックサブスクライバーの自動登録
    // #[AsMessageHandler]   - メッセージハンドラー登録
    // #[AsSchedule]         - スケジュールプロバイダー登録
    // #[AsEventListener]    - イベントリスナー登録
    // #[AsConfig]           - 設定クラス登録
}
```

## サービスプロバイダー

サービスプロバイダーを使用して関連するサービスグループを登録します：

```php
use WpPack\Component\Kernel\ServiceProvider;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(ContainerBuilder $container): void
    {
        $container->register(CacheInterface::class, RedisCache::class)
            ->setArguments([
                '$host' => '%cache.redis.host%',
                '$port' => '%cache.redis.port%',
            ]);
    }
}

// Kernel 内で：
protected function configureContainer(ContainerBuilder $container): void
{
    $container->addServiceProvider(new CacheServiceProvider());
    $container->addServiceProvider(new MailerServiceProvider());
}
```

## マルチサイトサポート

Kernel はマルチサイト環境を処理します：

```php
final class MyPluginKernel extends PluginKernel
{
    protected function configureContainer(ContainerBuilder $container): void
    {
        $container->discover(
            namespace: 'MyPlugin\\',
            directory: __DIR__ . '/src',
        );

        if (is_multisite()) {
            $container->setParameter('multisite.enabled', true);
            $container->setParameter('multisite.blog_id', get_current_blog_id());
        }
    }

    protected function onNetworkActivate(): void
    {
        // ネットワーク内のすべてのサイトでアクティベーションを実行
        $sites = get_sites(['number' => 0]);
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            $this->onActivate();
            restore_current_blog();
        }
    }
}
```

## テスト

Kernel はインテグレーションテスト用のヘルパーを提供します：

```php
use WpPack\Component\Kernel\Testing\KernelTestCase;

class MyPluginTest extends KernelTestCase
{
    protected function getKernelClass(): string
    {
        return MyPluginKernel::class;
    }

    public function testServiceIsRegistered(): void
    {
        $container = self::getContainer();
        $service = $container->get(MyService::class);

        $this->assertInstanceOf(MyService::class, $service);
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `PluginKernel` | WordPress プラグイン用ベース Kernel |
| `ThemeKernel` | WordPress テーマ用ベース Kernel |
| `ServiceProvider` | 関連するサービス登録をグループ化 |
| `Testing\KernelTestCase` | インテグレーションテスト用ベースクラス |
