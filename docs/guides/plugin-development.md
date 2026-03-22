# プラグイン開発ガイド

WpPack を使って WordPress プラグインを一から開発するための実践ガイドです。Kernel・DependencyInjection・Hook の各コンポーネントを組み合わせて、モダン PHP でプラグインを構築する方法を解説します。

## 使用するコンポーネント

| コンポーネント | 役割 |
|--------------|------|
| [Kernel](../components/kernel/README.md) | アプリケーションブートストラップ、ライフサイクル管理 |
| [DependencyInjection](../components/dependency-injection/README.md) | サービスコンテナ、自動検出、設定管理 |
| [Hook](../components/hook/README.md) | アトリビュートベースのアクション/フィルター登録 |

必要に応じて以下のコンポーネントも利用できます：

- [Admin](../components/admin/README.md) — 管理画面メニュー・通知
- [Rest](../components/rest/README.md) — REST API エンドポイント
- [Console](../components/console/) — WP-CLI コマンド
- [Plugin](../components/plugin/) — プラグイン管理画面フック

## ディレクトリ構成

```
my-plugin/
├── my-plugin.php          # エントリーポイント（WordPress プラグインヘッダー）
├── composer.json
├── config/
│   └── services.php       # サービス設定（ContainerConfigurator）
├── src/
│   ├── MyPlugin.php       # PluginInterface 実装
│   ├── Config/            # 設定クラス（通常のサービスとして自動登録）
│   ├── Service/           # ビジネスロジック
│   ├── Admin/
│   │   └── Page/          # 管理画面ページ
│   ├── Ajax/
│   │   └── Handler/       # AJAX ハンドラー
│   ├── DashboardWidget/   # ダッシュボードウィジェット
│   ├── Hook/
│   │   └── Subscriber/    # フックサブスクライバー
│   ├── Rest/
│   │   └── Controller/    # REST API コントローラー
│   ├── Routing/
│   │   └── Controller/    # フロントページコントローラー
│   ├── Setting/
│   │   └── Page/          # 設定ページ
│   ├── Shortcode/         # ショートコード
│   ├── Widget/            # ウィジェット
│   └── Command/           # WP-CLI
└── tests/
```

`composer.json` の例：

```json
{
    "name": "my-vendor/my-plugin",
    "type": "wordpress-plugin",
    "require": {
        "php": "^8.2",
        "wppack/kernel": "^1.0",
        "wppack/hook": "^1.0",
        "wppack/admin": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "MyPlugin\\": "src/"
        }
    }
}
```

## エントリーポイント

`my-plugin.php` は WordPress が読み込むプラグインメインファイルです。ここでは Composer オートロードの読み込みと、Kernel への登録のみを行います。

```php
<?php
/**
 * Plugin Name: My Plugin
 * Description: A WpPack-powered plugin.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use MyPlugin\MyPlugin;
use WpPack\Component\Kernel\Kernel;

// Kernel に登録（init フックで自動ブート、有効化・無効化フックも自動登録）
Kernel::registerPlugin(new MyPlugin(__FILE__));
```

`Kernel::registerPlugin()` は初回呼び出し時に Kernel インスタンスを自動生成し、`init` フック（priority 0）で `boot()` をスケジュールします。`PluginInterface::getFile()` が返すプラグインファイルパスを使って `register_activation_hook()` / `register_deactivation_hook()` が自動登録されます。

> 詳細: [Kernel コンポーネント](../components/kernel/README.md)

## PluginInterface の実装

`PluginInterface` は `ServiceProviderInterface` を拡張し、プラグインのライフサイクル全体を定義します。

```php
<?php

declare(strict_types=1);

namespace MyPlugin;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;
use WpPack\Component\Kernel\AbstractPlugin;

final class MyPlugin extends AbstractPlugin
{
    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        // Hook 基盤サービスの登録
        $builder->register(HookRegistry::class, HookRegistry::class)->setPublic(true);
        $builder->register(HookDiscovery::class, HookDiscovery::class)
            ->setPublic(true)
            ->autowire();

        // サービス設定ファイルの読み込み
        $builder->loadConfig(__DIR__ . '/../config/services.php');
    }

    public function getCompilerPasses(): array
    {
        return [
            new RegisterHookSubscribersPass(),
        ];
    }

    public function boot(Container $container): void
    {
        // コンテナ確定後の初期化処理
        // HookRegistry::register() はコンパイラーパスで自動呼び出しされる
    }

    public function onActivate(): void
    {
        // プラグイン有効化時の処理
        // 例: カスタムテーブルの作成、デフォルトオプションの設定
        update_option('my_plugin_version', '1.0.0');
    }

    public function onDeactivate(): void
    {
        // プラグイン無効化時の処理
        // 例: スケジュールイベントのクリア
        wp_clear_scheduled_hook('my_plugin_daily_event');
    }
}
```

### 各メソッドの役割

| メソッド | フェーズ | 説明 |
|---------|---------|------|
| `register()` | 登録 | ContainerBuilder にサービス・パラメータを登録 |
| `getCompilerPasses()` | コンパイル | コンパイラーパスを返す（フック登録等の自動処理） |
| `boot()` | ブート | コンテナ確定後の初期化処理 |
| `onActivate()` | 有効化時 | `Kernel::registerPlugin()` により `register_activation_hook()` に自動登録 |
| `onDeactivate()` | 無効化時 | `Kernel::registerPlugin()` により `register_deactivation_hook()` に自動登録 |

> 詳細: [Kernel コンポーネント — PluginInterface](../components/kernel/README.md#plugininterface)

## サービス登録

### services.php によるサービス設定

Symfony スタイルの `services.php` 設定ファイルで、サービスの自動検出とパラメータを宣言的に定義します。

```php
<?php
// config/services.php

declare(strict_types=1);

use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $services): void {
    // デフォルト設定
    $services->defaults()
        ->autowire()
        ->public();

    // パラメータの設定
    $services->param('my_plugin.dir', dirname(__DIR__));
    $services->param('my_plugin.url', plugin_dir_url(dirname(__DIR__) . '/my-plugin.php'));

    // src/ 配下の全クラスを自動検出・登録
    $services->load('MyPlugin\\', __DIR__ . '/../src/')
        ->exclude('*/tests/*');
};
```

`PluginInterface::register()` で `$builder->loadConfig()` を呼んで読み込みます：

```php
public function register(ContainerBuilder $builder): void
{
    $builder->loadConfig(__DIR__ . '/../config/services.php');
}
```

### ServiceDiscovery による自動検出

`ContainerConfigurator::load()` は内部的に `ServiceDiscovery` を使い、指定ディレクトリを再帰スキャンして**すべての具象クラス**を自動登録します。抽象クラス・インターフェース・`#[Exclude]` 付きクラスは除外されます。

```
config/services.php → ContainerConfigurator::load() → ServiceDiscovery::discover() → 全具象クラスを登録
```

特別なアトリビュートを付与しなくても、`src/` 配下のクラスは自動的にサービスとして登録されます。

### #[Exclude] による除外

自動検出から除外したいクラスには `#[Exclude]` を付与します。

```php
<?php

declare(strict_types=1);

namespace MyPlugin\Service;

use WpPack\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class InternalHelper
{
    // DI コンテナには登録されない
}
```

### #[Autowire] による依存注入

`#[Autowire]` アトリビュートでコンストラクタ引数に環境変数、パラメータ、WordPress options、PHP 定数を注入できます。

```php
<?php

declare(strict_types=1);

namespace MyPlugin\Service;

use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class ApiClient
{
    public function __construct(
        #[Autowire(env: 'MY_PLUGIN_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(param: 'my_plugin.url')]
        private readonly string $pluginUrl,
        #[Autowire(option: 'my_plugin_settings.notification_email')]
        private readonly string $notificationEmail,
        #[Autowire(constant: 'MY_PLUGIN_DEBUG')]
        private readonly bool $debug = false,
        #[Autowire(service: NotificationService::class)]
        private readonly NotificationService $notifier,
    ) {}
}
```

`#[Autowire]` のオプション：

| パラメータ | 説明 |
|-----------|------|
| `env` | 環境変数名（`$_ENV` / `getenv()` から解決） |
| `param` | コンテナパラメータ名（`ContainerBuilder::setParameter()` で設定した値） |
| `service` | サービス ID（クラス名） |
| `option` | WordPress option 名（`get_option()` から解決、ドット記法対応） |
| `constant` | PHP 定数名（`constant()` / `defined()` で解決） |

#### #[Env] / #[Option] / #[Constant] ショートハンド

`#[Autowire]` の `env`・`option`・`constant` には専用のショートハンドアトリビュートが用意されています。すべて `Autowire` を継承しています。

```php
use WpPack\Component\DependencyInjection\Attribute\Env;
use WpPack\Component\DependencyInjection\Attribute\Option;
use WpPack\Component\DependencyInjection\Attribute\Constant;

final class ApiClient
{
    public function __construct(
        #[Env('MY_PLUGIN_API_KEY')]
        private readonly string $apiKey,

        #[Option('my_plugin_settings.notification_email')]
        private readonly string $notificationEmail = 'admin@example.com',

        #[Constant('MY_PLUGIN_DEBUG')]
        private readonly bool $debug = false,
    ) {}
}
```

| ショートハンド | 等価な `#[Autowire]` |
|--------------|---------------------|
| `#[Env('NAME')]` | `#[Autowire(env: 'NAME')]` |
| `#[Option('key')]` | `#[Autowire(option: 'key')]` |
| `#[Constant('NAME')]` | `#[Autowire(constant: 'NAME')]` |

### #[AsAlias] によるインターフェースバインド

インターフェースに対する実装クラスを宣言的にバインドします。

```php
<?php

declare(strict_types=1);

namespace MyPlugin\Service;

use WpPack\Component\DependencyInjection\Attribute\AsAlias;

interface CacheInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value): void;
}

#[AsAlias(id: CacheInterface::class)]
final class TransientCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return get_transient($key);
    }

    public function set(string $key, mixed $value): void
    {
        set_transient($key, $value, 3600);
    }
}
```

`#[AsAlias]` は `IS_REPEATABLE` なので、1つのクラスに複数のインターフェースをバインドできます。

> 詳細: [DependencyInjection コンポーネント](../components/dependency-injection/README.md)

## フック登録

### フックサブスクライバーの作成

1. クラスに `#[AsHookSubscriber]` を付与
2. メソッドに Named Hook アトリビュート（`#[InitAction]`、`#[AdminMenuAction]` 等）を付与

```php
<?php

declare(strict_types=1);

namespace MyPlugin\Hook;

use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminNoticesAction;

#[AsHookSubscriber]
final class AdminSetup
{
    #[InitAction]
    public function registerPostType(): void
    {
        register_post_type('my_plugin_item', [
            'labels' => [
                'name' => 'Items',
                'singular_name' => 'Item',
            ],
            'public' => true,
            'show_in_rest' => true,
        ]);
    }

    #[AdminMenuAction]
    public function addSettingsPage(): void
    {
        add_options_page(
            'My Plugin Settings',
            'My Plugin',
            'manage_options',
            'my-plugin-settings',
            [$this, 'renderSettingsPage'],
        );
    }

    #[AdminNoticesAction]
    public function showSetupNotice(): void
    {
        if (get_option('my_plugin_api_key')) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p>%s</p></div>',
            'My Plugin: API キーを設定してください。',
        );
    }

    public function renderSettingsPage(): void
    {
        // 設定ページの描画
    }
}
```

### 自動検出・バインドの仕組み

フック登録は以下の流れで自動処理されます：

1. `ServiceDiscovery::discover()` が `src/` 配下の全具象クラスをコンテナに登録
2. `RegisterHookSubscribersPass` が `#[AsHookSubscriber]` 付きサービスを検出
3. コンパイル時に `HookDiscovery::register()` でフックメソッドを収集
4. `HookRegistry::register()` で WordPress の `add_action()` / `add_filter()` に登録

```
ServiceDiscovery → ContainerBuilder → RegisterHookSubscribersPass → HookDiscovery → HookRegistry → WordPress
```

`RegisterHookSubscribersPass` は `#[AsHookSubscriber]` アトリビュートまたは `hook.subscriber` タグを持つサービスを自動検出するため、手動でのフック登録は不要です。

### Named Hook アトリビュート

Hook コンポーネントはすべての Named Hook アトリビュートを一元管理しています。ライフサイクルフック（`init`、`admin_init` 等）は `Hook\Attribute\Action\` 直下に、ドメイン固有のフックはコンポーネント別サブディレクトリ（`Hook\Attribute\{Name}\`）に配置されています。

```php
// ライフサイクルフック（Hook\Attribute\Action\）
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\Action\AdminInitAction;

// Admin ドメインフック（Hook\Attribute\Admin\）
use WpPack\Component\Hook\Attribute\Admin\Action\AdminMenuAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminEnqueueScriptsAction;
use WpPack\Component\Hook\Attribute\Admin\Action\AdminNoticesAction;

// Plugin ドメインフック（Hook\Attribute\Plugin\）
use WpPack\Component\Hook\Attribute\Plugin\Filter\PluginActionLinksFilter;

// 汎用的な Hook アトリビュート（Named Hook がない場合）
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
```

Named Hook アトリビュートが定義されていないフックには、汎用の `#[Action('hook_name')]` / `#[Filter('hook_name')]` を使用できます。

> 詳細: [Hook コンポーネント](../components/hook/README.md)、[Named Hook 規約](../components/hook/named-hook-conventions.md)

## 設定管理

### 設定クラスの定義

DI コンポーネントの `#[Env]`・`#[Option]`・`#[Constant]` アトリビュートを使い、readonly クラスで型安全な設定を定義します。設定クラスは通常のサービスとしてディレクトリスキャンで自動登録されるため、特別なマーカーアトリビュートは不要です。

```php
<?php

declare(strict_types=1);

namespace MyPlugin\Config;

use WpPack\Component\DependencyInjection\Attribute\Env;
use WpPack\Component\DependencyInjection\Attribute\Option;
use WpPack\Component\DependencyInjection\Attribute\Constant;

final readonly class PluginConfig
{
    public function __construct(
        #[Env('MY_PLUGIN_API_KEY')]
        public string $apiKey = '',

        #[Option('my_plugin_settings.notification_email')]
        public string $notificationEmail = 'admin@example.com',

        #[Constant('MY_PLUGIN_DEBUG')]
        public bool $debug = false,

        #[Option('my_plugin_settings.max_items')]
        public int $maxItems = 100,
    ) {}
}
```

### 値の解決元

| アトリビュート | 解決元 | 用途 |
|--------------|-------|------|
| `#[Env('NAME')]` | `$_ENV` / `getenv()` | API キー、シークレット等の機密情報 |
| `#[Option('key')]` | `get_option()` | WordPress 管理画面で変更する設定 |
| `#[Constant('NAME')]` | PHP 定数 | `wp-config.php` で定義するサーバー固有設定 |

`#[Option]` はドット記法でネストした値にアクセスできます。例えば `#[Option('my_plugin_settings.notification_email')]` は `get_option('my_plugin_settings')['notification_email']` を解決します。

すべてのアトリビュートで、値が存在しない場合はコンストラクタのデフォルト値にフォールバックします。デフォルト値もない場合は `RuntimeException` がスローされます。

### 設定クラスの利用

設定クラスは `ServiceDiscovery` によってコンテナに自動登録されるため、他のサービスからコンストラクタインジェクションで利用できます。

```php
#[AsHookSubscriber]
final class ApiIntegration
{
    public function __construct(
        private readonly PluginConfig $config,
    ) {}

    #[InitAction]
    public function connectApi(): void
    {
        if ($this->config->apiKey === '') {
            return;
        }

        // API 接続処理
    }
}
```

> 詳細: [DependencyInjection コンポーネント](../components/dependency-injection/README.md)

## 有効化・無効化

### onActivate() / onDeactivate()

`PluginInterface` の `onActivate()` と `onDeactivate()` は、`Kernel::registerPlugin()` により自動的に `register_activation_hook()` / `register_deactivation_hook()` に登録されます。エントリーポイントで手動登録する必要はありません。

```php
final class MyPlugin extends AbstractPlugin
{
    // ...

    public function __construct(
        string $pluginFile,
        private readonly DatabaseManager $db,
    ) {
        parent::__construct($pluginFile);
    }

    public function onActivate(): void
    {
        // カスタムテーブルの作成
        $tableName = $this->db->prefix() . 'my_plugin_logs';
        $charsetCollate = $this->db->charsetCollate();

        $sql = "CREATE TABLE {$tableName} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // デフォルトオプションの設定
        add_option('my_plugin_settings', [
            'notification_email' => get_option('admin_email'),
            'max_items' => 100,
        ]);

        // バージョン記録
        update_option('my_plugin_version', '1.0.0');
    }

    public function onDeactivate(): void
    {
        // スケジュールイベントのクリア
        wp_clear_scheduled_hook('my_plugin_daily_event');
    }
}
```

### Kernel による自動登録

`Kernel::registerPlugin(new MyPlugin(__FILE__))` を呼ぶだけで、`PluginInterface::getFile()` が返すプラグインファイルパスを使って `onActivate()` / `onDeactivate()` が WordPress の有効化・無効化フックに自動登録されます。手動で `register_activation_hook()` / `register_deactivation_hook()` を呼ぶ必要はありません。

> [!NOTE]
> `onActivate()` / `onDeactivate()` はコンテナのブート前に呼ばれるため、DI コンテナに依存しない処理を記述してください。

## 実践例：設定ページ付きプラグイン

以下は、管理画面に設定ページを持ち、設定値を使って動作するプラグインの完全な実装例です。

### ファイル構成

```
my-notification-plugin/
├── my-notification-plugin.php
├── composer.json
├── config/
│   └── services.php
├── src/
│   ├── MyNotificationPlugin.php
│   ├── Config/
│   │   └── NotificationConfig.php
│   ├── Service/
│   │   └── NotificationSender.php
│   └── Hook/
│       ├── SettingsPage.php
│       └── NotificationHooks.php
└── tests/
```

### my-notification-plugin.php

```php
<?php
/**
 * Plugin Name: My Notification Plugin
 * Description: Custom notification system powered by WpPack.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use MyNotificationPlugin\MyNotificationPlugin;
use WpPack\Component\Kernel\Kernel;

Kernel::registerPlugin(new MyNotificationPlugin(__FILE__));
```

### config/services.php

```php
<?php

declare(strict_types=1);

use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $services): void {
    $services->defaults()
        ->autowire()
        ->public();

    $services->load('MyNotificationPlugin\\', __DIR__ . '/../src/');
};
```

### src/MyNotificationPlugin.php

```php
<?php

declare(strict_types=1);

namespace MyNotificationPlugin;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;
use WpPack\Component\Kernel\AbstractPlugin;

final class MyNotificationPlugin extends AbstractPlugin
{
    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->register(HookRegistry::class, HookRegistry::class)->setPublic(true);
        $builder->register(HookDiscovery::class, HookDiscovery::class)
            ->setPublic(true)
            ->autowire();

        $builder->loadConfig(__DIR__ . '/../config/services.php');
    }

    public function getCompilerPasses(): array
    {
        return [
            new RegisterHookSubscribersPass(),
        ];
    }

    public function boot(Container $container): void {}

    public function onActivate(): void
    {
        add_option('my_notification_settings', [
            'email' => get_option('admin_email'),
            'enabled' => true,
        ]);
    }

    public function onDeactivate(): void {}
}
```

### src/Config/NotificationConfig.php

```php
<?php

declare(strict_types=1);

namespace MyNotificationPlugin\Config;

use WpPack\Component\DependencyInjection\Attribute\Option;

final readonly class NotificationConfig
{
    public function __construct(
        #[Option('my_notification_settings.email')]
        public string $email = '',

        #[Option('my_notification_settings.enabled')]
        public bool $enabled = true,
    ) {}
}
```

### src/Service/NotificationSender.php

```php
<?php

declare(strict_types=1);

namespace MyNotificationPlugin\Service;

use MyNotificationPlugin\Config\NotificationConfig;

final class NotificationSender
{
    public function __construct(
        private readonly NotificationConfig $config,
    ) {}

    public function send(string $subject, string $message): bool
    {
        if (!$this->config->enabled || $this->config->email === '') {
            return false;
        }

        return wp_mail($this->config->email, $subject, $message);
    }
}
```

### src/Hook/SettingsPage.php

```php
<?php

declare(strict_types=1);

namespace MyNotificationPlugin\Hook;

use WpPack\Component\Hook\Attribute\Admin\Action\AdminMenuAction;
use WpPack\Component\Hook\Attribute\Action\AdminInitAction;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

#[AsHookSubscriber]
final class SettingsPage
{
    #[AdminInitAction]
    public function registerSettings(): void
    {
        register_setting('my_notification_settings_group', 'my_notification_settings');

        add_settings_section(
            'my_notification_main',
            'Notification Settings',
            static fn() => null,
            'my-notification-settings',
        );

        add_settings_field(
            'email',
            'Notification Email',
            [$this, 'renderEmailField'],
            'my-notification-settings',
            'my_notification_main',
        );

        add_settings_field(
            'enabled',
            'Enable Notifications',
            [$this, 'renderEnabledField'],
            'my-notification-settings',
            'my_notification_main',
        );
    }

    #[AdminMenuAction]
    public function addMenuPage(): void
    {
        add_options_page(
            'Notification Settings',
            'Notifications',
            'manage_options',
            'my-notification-settings',
            [$this, 'renderPage'],
        );
    }

    public function renderPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Notification Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('my_notification_settings_group');
        do_settings_sections('my-notification-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function renderEmailField(): void
    {
        $options = get_option('my_notification_settings', []);
        $value = $options['email'] ?? '';
        printf(
            '<input type="email" name="my_notification_settings[email]" value="%s" class="regular-text">',
            esc_attr($value),
        );
    }

    public function renderEnabledField(): void
    {
        $options = get_option('my_notification_settings', []);
        $checked = ($options['enabled'] ?? true) ? 'checked' : '';
        printf(
            '<input type="checkbox" name="my_notification_settings[enabled]" value="1" %s>',
            $checked,
        );
    }
}
```

### src/Hook/NotificationHooks.php

```php
<?php

declare(strict_types=1);

namespace MyNotificationPlugin\Hook;

use MyNotificationPlugin\Service\NotificationSender;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;

#[AsHookSubscriber]
final class NotificationHooks
{
    public function __construct(
        private readonly NotificationSender $sender,
    ) {}

    #[Action('transition_post_status')]
    public function onPostPublished(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        $this->sender->send(
            sprintf('New post published: %s', $post->post_title),
            sprintf('A new post "%s" has been published.', $post->post_title),
        );
    }
}
```

この例では以下の WpPack パターンを使用しています：

- **PluginInterface** — ライフサイクル管理とサービス登録
- **services.php + ContainerConfigurator** — Symfony スタイルのサービス設定
- **ディレクトリスキャン** — `src/` 配下の全クラスを自動検出・登録
- **#[Option]** — WordPress options からの型安全な設定読み込み
- **#[AsHookSubscriber]** — フックの自動検出・登録
- **Named Hook アトリビュート** — `#[AdminMenuAction]`、`#[AdminInitAction]` による宣言的なフック登録
- **汎用 Hook アトリビュート** — `#[Action('transition_post_status')]` による任意フックの利用
- **コンストラクタインジェクション** — 設定やサービスの自動注入
