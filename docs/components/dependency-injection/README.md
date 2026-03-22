# DependencyInjection コンポーネント

**パッケージ:** `wppack/dependency-injection`
**名前空間:** `WpPack\Component\DependencyInjection\`
**レイヤー:** Infrastructure

PSR-11 準拠のサービスコンテナです。ディレクトリスキャンによる自動サービス登録、オートワイヤリング、Symfony スタイルの `ContainerConfigurator`、インターフェースバインディング、タグ付きサービス、コンパイラーパス、ファクトリサービス、サービスデコレーション、遅延サービス、WordPress グローバルのインジェクタブルサービスとしての提供などを提供します。

## インストール

```bash
composer require wppack/dependency-injection
```

## 基本コンセプト

### Before（従来の WordPress）

```php
global $wpdb;

class UserRepository {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function find($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->users} WHERE ID = %d", $id
        ));
    }
}
```

### After（WpPack）

```php
final class UserRepository
{
    public function __construct(
        private readonly \wpdb $wpdb,
        private readonly CacheInterface $cache,
    ) {}

    public function find(int $id): ?User
    {
        return $this->cache->get("user_{$id}", fn() =>
            $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->users} WHERE ID = %d", $id
            ))
        );
    }
}
```

## サービスコンテナ

```php
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;

$builder = new ContainerBuilder();

// サービスを登録
$builder->register(PostRepository::class);
$builder->register(PostService::class);

// コンテナをコンパイル
$container = $builder->compile();

// サービスを取得
$postService = $container->get(PostService::class);
```

## WordPress ライフサイクルとコンテナ

WordPress 全体で **1つのコンテナ** を使用します。コンテナのライフサイクルは3つのフェーズに分かれます：

| フェーズ | オブジェクト | 操作 | タイミング |
|---------|------------|------|----------|
| 登録 | ContainerBuilder | register, discover, addCompilerPass | 〜 after_setup_theme |
| コンパイル | ContainerBuilder → Container | compile() | init |
| 実行 | Container（読み取り専用） | get, has | init 以降 |

### WordPress フック実行順との対応

```
WordPress コア読み込み         $wpdb 等が利用可能
MU プラグイン読み込み       ─┐
各プラグインファイル実行     │ 登録フェーズ
plugins_loaded              │ Kernel 初期化 & Plugin/Theme 登録
テーマ functions.php 実行    │
after_setup_theme          ─┘
init                        ← compile() → Container 確定
wp_loaded 以降               実行フェーズ（boot 実行）
```

### Kernel による管理

Kernel がコンテナのライフサイクルを管理します。各プラグインは `PluginInterface`、テーマは `ThemeInterface` として Kernel に登録します：

```php
Kernel::registerPlugin(new MyPlugin(__FILE__));
Kernel::registerTheme(new MyTheme(__FILE__));
```

### PluginInterface

`ServiceProviderInterface` を拡張し、コンパイラーパスの提供・ブート処理・アクティベーション/ディアクティベーションのフックを追加します：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\Kernel\AbstractPlugin;

final class MyPlugin extends AbstractPlugin
{
    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->loadConfig(__DIR__ . '/config/services.php');
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
        // プラグイン有効化時の処理
    }

    public function onDeactivate(): void
    {
        // プラグイン無効化時の処理
    }
}
```

### ThemeInterface

`ServiceProviderInterface` を拡張し、コンパイラーパスの提供・ブート処理を追加します：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\Kernel\AbstractTheme;

final class MyTheme extends AbstractTheme
{
    public function __construct(string $themeFile)
    {
        parent::__construct($themeFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->loadConfig(__DIR__ . '/config/services.php');
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

### 注意事項

- `compile()` 後は Container が読み取り専用になり、サービスの登録・変更はできません
- `init` より前にすべての登録を完了してください
- 本番環境では PhpDumper でキャッシュし compile をスキップ可能です（「[本番環境用のコンテナコンパイル](#本番環境用のコンテナコンパイル)」を参照）
- `PluginInterface` / `ThemeInterface` の詳細は Kernel コンポーネントを参照してください

## オートワイヤリング

コンストラクタの型ヒントを使用して依存関係を自動的に解決します：

```php
final class PostService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {}
}

// PostRepository、CacheInterface、LoggerInterface は
// コンテナに登録されていれば自動的にインジェクトされます
$builder->register(PostService::class)->autowire();
```

## アトリビュートベースのサービス設定

### 自動登録

スキャン対象ディレクトリ内のすべてのクラスは、Symfony と同様にデフォルトでサービスとして登録されます。特別な登録マーカーは不要です：

```php
final class PostRepository
{
    public function __construct(
        private readonly \wpdb $database,
    ) {}

    public function find(int $id): ?Post
    {
        // ...
    }
}
```

### `#[Exclude]`

サービスとして登録したくないクラスに使用します：

```php
use WpPack\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class PostDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
    ) {}
}
```

### `#[Autowire]`

特定のパラメータ値を明示的にインジェクトします：

```php
use WpPack\Component\DependencyInjection\Attribute\Autowire;

final class S3Client
{
    public function __construct(
        #[Autowire(env: 'AWS_REGION')]
        private readonly string $region,

        #[Autowire(env: 'S3_BUCKET')]
        private readonly string $bucket,

        #[Autowire(param: 'app.debug')]
        private readonly bool $debug,

        #[Autowire(option: 'my_plugin_settings.region')]
        private readonly string $settingsRegion,

        #[Autowire(constant: 'MY_PLUGIN_VERSION')]
        private readonly string $version,
    ) {}
}
```

### ショートハンドアトリビュート

`#[Autowire]` の代わりに、よく使うパターンにはショートハンドアトリビュートを使用できます。これらは `#[Autowire]` を拡張しています：

```php
use WpPack\Component\DependencyInjection\Attribute\Env;
use WpPack\Component\DependencyInjection\Attribute\Option;
use WpPack\Component\DependencyInjection\Attribute\Constant;

final readonly class PluginConfig
{
    public function __construct(
        #[Env('MY_PLUGIN_API_KEY')]
        public string $apiKey = '',

        #[Option('my_plugin_settings.email')]
        public string $email = '',

        #[Constant('MY_PLUGIN_DEBUG')]
        public bool $debug = false,
    ) {}
}
```

| アトリビュート | 同等の `#[Autowire]` | 説明 |
|--------------|---------------------|------|
| `#[Env('NAME')]` | `#[Autowire(env: 'NAME')]` | 環境変数を注入 |
| `#[Option('NAME')]` | `#[Autowire(option: 'NAME')]` | WordPress option を注入 |
| `#[Constant('NAME')]` | `#[Autowire(constant: 'NAME')]` | PHP 定数を注入 |

### `#[AsAlias]`

インターフェースを具象実装にバインドします：

```php
use WpPack\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CacheInterface::class)]
final class WordPressTransientCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return get_transient($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return set_transient($key, $value, $ttl);
    }
}
```

## サービスディスカバリー

ディレクトリ内のすべてのクラスをサービスとして自動登録します。`#[Exclude]` が付与されたクラスは除外されます：

```php
use WpPack\Component\DependencyInjection\ServiceDiscovery;

$discovery = new ServiceDiscovery($builder, autowire: true, public: true);

// ディレクトリをスキャン（全クラスが登録される）
$discovery->discover(
    __DIR__ . '/src',
    'App\\',
);

// 特定のクラスを除外
$discovery->discover(
    __DIR__ . '/src',
    'App\\',
    excludes: ['Dto/*', 'Entity/*'],
);
```

`#[Exclude]` アトリビュートを付与したクラスも自動的に除外されます。

## ContainerConfigurator

Symfony スタイルの `services.php` 設定ファイルでサービスを構成します。`ContainerConfigurator` を使用すると、サービスの登録・デフォルト設定・パラメータ設定をひとつのファイルにまとめられます：

```php
// config/services.php
use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;
use WpPack\Component\Hook\HookRegistry;

return static function (ContainerConfigurator $services): void {
    // デフォルト設定（全サービスに適用）
    $services->defaults()->autowire()->public();

    // ディレクトリスキャンによる一括登録
    $services->load('MyPlugin\\', dirname(__DIR__) . '/src/');

    // 個別サービスの追加設定
    $services->set(HookRegistry::class);

    // パラメータの設定
    $services->param('my_plugin.dir', dirname(__DIR__, 2));
};
```

### ContainerBuilder での読み込み

`loadConfig()` メソッドで設定ファイルを読み込みます：

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\AbstractPlugin;

final class MyPlugin extends AbstractPlugin
{
    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->loadConfig(__DIR__ . '/config/services.php');
    }

    // ...
}
```

### Configurator API

`ContainerConfigurator` は以下のメソッドチェーンを提供します：

```php
return static function (ContainerConfigurator $services): void {
    // defaults() - 全サービスのデフォルト設定
    $services->defaults()
        ->autowire()    // オートワイヤリングを有効化
        ->public();     // パブリックに設定

    // load() - ディレクトリスキャン
    $services->load('App\\', dirname(__DIR__) . '/src/')
        ->exclude('Dto/*');

    // set() - 個別サービスの設定
    $services->set(MyService::class)
        ->arg('$apiKey', '%env(API_KEY)%')
        ->tag('app.handler')
        ->lazy();

    // param() - パラメータの設定
    $services->param('app.debug', true);
};
```

## タグ付きサービスとコンパイラーパス

### タグ付きサービス

一括操作のために関連するサービスをグループ化します：

タグ付けは `ContainerConfigurator` またはコンパイラーパス内で行います：

```php
// config/services.php
return static function (ContainerConfigurator $services): void {
    $services->defaults()->autowire()->public();
    $services->load('MyPlugin\\', dirname(__DIR__) . '/src/');

    $services->set(EmailNotification::class)->tag('notification.channel');
    $services->set(SlackNotification::class)->tag('notification.channel');
};
```

```php
final class EmailNotification implements NotificationChannel
{
    public function send(string $to, string $message): bool
    {
        return wp_mail($to, 'Notification', $message);
    }
}

final class SlackNotification implements NotificationChannel
{
    public function send(string $to, string $message): bool
    {
        // Slack に送信
    }
}
```

### コンパイラーパス

コンパイル時にコンテナを変更します：

```php
use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;

final class RegisterNotificationChannelsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        $taggedServices = $builder->findTaggedServiceIds('notification.channel');

        $manager = $builder->findDefinition(NotificationManager::class);
        foreach ($taggedServices as $id => $tags) {
            $manager->addMethodCall('addChannel', [new Reference($id)]);
        }
    }
}

$builder->addCompilerPass(new RegisterNotificationChannelsPass());
```

### 既存サービスの引数修正

コンパイラーパスで既存サービスの設定を変更できます：

```php
final class OverrideLogLevelPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        if ($builder->hasDefinition(ErrorLogHandler::class)) {
            $builder->findDefinition(ErrorLogHandler::class)
                ->setArgument('$level', 'debug');
        }
    }
}
```

## ファクトリサービス

複雑なサービス生成を処理します：

```php
final class LoggerFactory
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function createLogger(string $channel): LoggerInterface
    {
        $handler = $this->config->get('app.debug')
            ? new FileHandler(WP_CONTENT_DIR . '/logs/debug.log')
            : new ErrorLogHandler();

        return new Logger($channel, [$handler]);
    }
}
```

## サービスデコレーション

既存のサービスを変更せずに拡張します：

```php
final class CacheLogger implements CacheInterface
{
    public function __construct(
        private readonly CacheInterface $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function get(string $key): mixed
    {
        $this->logger->debug("Cache get: {$key}");
        return $this->inner->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->logger->debug("Cache set: {$key}");
        return $this->inner->set($key, $value, $ttl);
    }
}
```

## パラメータ

コンテナにスカラー値を登録し、サービス定義で参照できます。

### 基本的なパラメータ

```php
$builder->setParameter('app.debug', true);
$builder->setParameter('app.name', 'My Plugin');

// パラメータの取得
$debug = $builder->getParameter('app.debug');
$exists = $builder->hasParameter('app.name');
```

### `%param%` 構文

サービス定義の引数に `%param_name%` 構文でパラメータを埋め込めます。コンパイル時に実際の値に解決されます：

```php
$builder->setParameter('app.name', 'My Plugin');

$builder->register(AppService::class)
    ->setArgument(0, '%app.name%');
```

### `%env(VAR)%` 構文

環境変数は `%env(VAR)%` 構文で参照できます。値は実行時に解決されます：

```php
$builder->register(S3Client::class)
    ->setArgument('$region', '%env(AWS_REGION)%')
    ->setArgument('$bucket', '%env(S3_BUCKET)%');
```

`#[Autowire]` アトリビュートでも同様に使用できます：

```php
final class S3Client
{
    public function __construct(
        #[Autowire(env: 'AWS_REGION')]
        private readonly string $region,

        #[Autowire(param: 'app.debug')]
        private readonly bool $debug,
    ) {}
}
```

## ServiceProviderInterface

サービス登録をカプセル化するためのインターフェースです。プラグインやライブラリが提供するサービス群をひとまとめに登録できます。

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

final class PaymentServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(PaymentGateway::class)
            ->autowire()
            ->setPublic(true);

        $builder->register(PaymentProcessor::class)
            ->autowire();

        $builder->register(PaymentLogger::class)
            ->autowire()
            ->addTag('monolog.logger');

        $builder->setAlias(PaymentGatewayInterface::class, PaymentGateway::class);
    }
}

// 使用
$builder->addServiceProvider(new PaymentServiceProvider());
```

### ライブラリ向けの活用

ライブラリやプラグインのブートストラップ時に複数のプロバイダーを登録します：

```php
$builder->addServiceProvider(new WordPressServiceProvider());
$builder->addServiceProvider(new PaymentServiceProvider());
$builder->addServiceProvider(new NotificationServiceProvider());
```

## WordPress との統合

WordPress グローバルとオブジェクトをインジェクタブルサービスとして登録します：

```php
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;

$builder->addServiceProvider(new WordPressServiceProvider());

// 以下のサービスが利用可能になります：
// - wpdb ($wpdb インスタンス)
// - wp_filesystem (WP_Filesystem_Base インスタンス)
```

`WordPressServiceProvider` はファクトリメソッドを使用して、WordPress のグローバル変数をサービスとして安全に提供します。これにより、`global $wpdb` の直接使用を排除し、テスト時にモックへ差し替え可能になります。

### カスタム WordPress ファクトリ

```php
final class WordPressFactory
{
    public function getWpdb(): \wpdb
    {
        global $wpdb;
        return $wpdb;
    }

    public function getCurrentUser(): \WP_User
    {
        return wp_get_current_user();
    }
}
```

## 遅延サービス

初回使用時にのみインスタンス化されるサービス。`ContainerConfigurator` で `lazy()` を指定します：

```php
// config/services.php
return static function (ContainerConfigurator $services): void {
    $services->defaults()->autowire()->public();
    $services->load('MyPlugin\\', dirname(__DIR__) . '/src/');

    $services->set(HeavyService::class)->lazy();
};
```

```php
final class HeavyService
{
    public function __construct(
        private readonly LargeDatasetLoader $loader,
    ) {}

    public function process(): void
    {
        // サービスとその依存関係はここで初めて作成されます
    }
}
```

## 本番環境用のコンテナコンパイル

### PhpDumper の目的

`PhpDumper` はコンパイル済みコンテナを PHP コードとしてダンプします。本番環境でダンプ済みのコンテナを使用することで、毎リクエストでのサービス解決・コンパイルをスキップし、パフォーマンスを向上させます。

### 使い方

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Dumper\PhpDumper;

// ビルドステップ（デプロイ時に実行）
$builder = new ContainerBuilder();
// ... サービスを登録 ...
$builder->compile();

$dumper = new PhpDumper($builder);
$code = $dumper->dump(['class' => 'CachedContainer']);
file_put_contents(__DIR__ . '/cache/container.php', $code);
```

### 本番環境での使用

```php
// 本番環境でキャッシュ済みコンテナを使用
$cachePath = __DIR__ . '/cache/container.php';

if (file_exists($cachePath)) {
    require_once $cachePath;
    $container = new \CachedContainer();
} else {
    // 開発環境ではオンデマンドでビルド
    $builder = new ContainerBuilder();
    // ... サービスを登録 ...
    $container = $builder->compile();
}
```

### dump() オプション

```php
$dumper->dump([
    'class' => 'CachedContainer',      // 生成されるクラス名（デフォルト: ProjectServiceContainer）
    'namespace' => 'App\\Container',    // 名前空間
    'base_class' => 'BaseContainer',    // 基底クラス
]);
```

## コンポーネント連携: Mailer

Mailer コンポーネントは `RegisterTransportFactoriesPass` コンパイラーパスを提供し、`mailer.transport_factory` タグ付きサービスを `Transport` に自動注入します。

```php
use WpPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Component\Mailer\Mailer;

$builder = new ContainerBuilder();

// コンパイラーパスを登録
$builder->addCompilerPass(new RegisterTransportFactoriesPass());

// トランスポートファクトリを登録（タグ付き）
$builder->register(NativeTransportFactory::class)->addTag('mailer.transport_factory');
$builder->register(SesTransportFactory::class)->addTag('mailer.transport_factory');

// Transport サービス
$builder->register(Transport::class);

// TransportInterface をファクトリ経由で解決
$builder->register(TransportInterface::class)
    ->setFactory([new Reference(Transport::class), 'fromString'])
    ->addArgument(MAILER_DSN);

// Mailer サービス
$builder->register(Mailer::class)
    ->addArgument(new Reference(TransportInterface::class));
```

詳細は [Mailer コンポーネント](../mailer/) を参照。

## コンポーネント連携: Logger

Logger コンポーネントは `RegisterLoggerPass` コンパイラーパスと `#[LoggerChannel]` アトリビュートを提供し、チャンネルベースのロガー注入を自動化します。

```php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Logger\LoggerFactory;
use WpPack\Component\Logger\Handler\ErrorLogHandler;
use WpPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use Psr\Log\LoggerInterface;

$builder = new ContainerBuilder();

// LoggerFactory をデフォルトハンドラー付きで登録
$builder->register(LoggerFactory::class)
    ->addArgument([new ErrorLogHandler()]);

// デフォルトロガー（channel: 'app'）
$builder->register(LoggerInterface::class)
    ->setFactory([new Reference(LoggerFactory::class), 'create'])
    ->setArgument(0, 'app');

// #[LoggerChannel] アトリビュートの自動解決
$builder->addCompilerPass(new RegisterLoggerPass());
```

サービスクラスで `#[LoggerChannel]` を使用すると、チャンネル別のロガーが自動的に注入されます：

```php
use Psr\Log\LoggerInterface;
use WpPack\Component\Logger\Attribute\LoggerChannel;

final class PaymentService
{
    public function __construct(
        #[LoggerChannel('payment')]
        private readonly LoggerInterface $logger,
    ) {}
}
```

詳細は [Logger コンポーネント](../logger/) を参照。

## テスト方法

### ユニットテスト（モック使用）

DI コンテナを使わず、依存関係をモックしてテストします：

```php
use PHPUnit\Framework\TestCase;

final class PostServiceTest extends TestCase
{
    public function testCreatesPost(): void
    {
        $repository = $this->createMock(PostRepository::class);
        $repository->expects(self::once())
            ->method('save')
            ->willReturn(true);

        $service = new PostService($repository);
        $result = $service->create(['title' => 'Test']);

        self::assertTrue($result);
    }
}
```

### 統合テスト（コンテナ使用）

実際のコンテナを構築してサービスの統合をテストします：

```php
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;

final class AppIntegrationTest extends TestCase
{
    public function testServiceWiring(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(PostRepository::class)->autowire()->setPublic(true);
        $builder->register(PostService::class)->autowire()->setPublic(true);

        $container = $builder->compile();

        $service = $container->get(PostService::class);
        self::assertInstanceOf(PostService::class, $service);
    }
}
```

### コンパイラーパスのテスト

```php
final class RegisterHandlersPassTest extends TestCase
{
    public function testCollectsTaggedHandlers(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('handler.a', HandlerA::class)
            ->addTag('app.handler');
        $builder->register('handler.b', HandlerB::class)
            ->addTag('app.handler');
        $builder->register(HandlerManager::class);

        $builder->addCompilerPass(new RegisterHandlersPass());
        $container = $builder->compile();

        $manager = $container->get(HandlerManager::class);
        self::assertCount(2, $manager->getHandlers());
    }
}
```

## デバッグ

### ServiceNotFoundException

サービスが見つからない場合にスローされます：

```php
use WpPack\Component\DependencyInjection\Exception\ServiceNotFoundException;

try {
    $container->get('unknown.service');
} catch (ServiceNotFoundException $e) {
    // "Service "unknown.service" not found."
}
```

**よくある原因：**

- サービスが登録されていない
- サービスが `public` に設定されていない（`setPublic(true)` が必要）
- エイリアスのタイプミス

`ContainerBuilder::findDefinition()` でもコンパイル前にサービスの存在を確認できます：

```php
try {
    $builder->findDefinition('my.service');
} catch (ServiceNotFoundException $e) {
    // サービスが登録されていない
}
```

### ParameterNotFoundException

パラメータが見つからない場合にスローされます：

```php
use WpPack\Component\DependencyInjection\Exception\ParameterNotFoundException;

try {
    $builder->getParameter('unknown.param');
} catch (ParameterNotFoundException $e) {
    // "Parameter "unknown.param" not found."
}
```

### デバッグの手順

1. **サービスが登録されているか確認**
   ```php
   $builder->hasDefinition(MyService::class); // bool
   ```

2. **定義の内容を確認**
   ```php
   $def = $builder->findDefinition(MyService::class);
   $def->getClass();      // クラス名
   $def->isAutowired();   // オートワイヤリング有効か
   $def->isPublic();      // パブリックか
   $def->getTags();       // タグ一覧
   ```

3. **タグ付きサービスの一覧**
   ```php
   $tagged = $builder->findTaggedServiceIds('app.handler');
   // ['handler.a' => [...], 'handler.b' => [...]]
   ```

## 実践例

`PluginInterface` を使ったプラグインの完全なワークフロー例です：

```php
// my-plugin.php（プラグインメインファイル）
use WpPack\Component\Kernel\Kernel;

Kernel::registerPlugin(new MyPlugin(__FILE__));
```

```php
// config/services.php
use WpPack\Component\DependencyInjection\Configurator\ContainerConfigurator;
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;

return static function (ContainerConfigurator $services): void {
    $services->defaults()->autowire()->public();
    $services->load('MyPlugin\\', dirname(__DIR__) . '/src/');

    $services->param('plugin.dir', dirname(__DIR__));
    $services->param('plugin.url', plugin_dir_url(dirname(__DIR__) . '/my-plugin.php'));
};
```

```php
// MyPlugin.php
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;
use WpPack\Component\Kernel\AbstractPlugin;

final class MyPlugin extends AbstractPlugin
{
    public function __construct(string $pluginFile)
    {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        // WordPress サービスの登録
        $builder->addServiceProvider(new WordPressServiceProvider());

        // サービス設定の読み込み
        $builder->loadConfig(__DIR__ . '/config/services.php');
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

## API リファレンス

### ContainerBuilder

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `register(string $id, ?string $class = null)` | `Definition` | サービスを登録 |
| `findDefinition(string $id)` | `Definition` | 定義を取得（未登録時は `ServiceNotFoundException`） |
| `hasDefinition(string $id)` | `bool` | 定義が存在するか |
| `all()` | `array<string, Definition>` | 全定義を取得 |
| `findTaggedServiceIds(string $tag)` | `array` | タグ付きサービスID一覧 |
| `addCompilerPass(CompilerPassInterface $pass)` | `self` | コンパイラーパスを追加 |
| `getCompilerPasses()` | `CompilerPassInterface[]` | コンパイラーパス一覧 |
| `compile()` | `Container` | コンテナをコンパイル |
| `loadConfig(string $path)` | `self` | services.php 設定ファイルを読み込み |
| `setParameter(string $name, mixed $value)` | `self` | パラメータを設定 |
| `getParameter(string $name)` | `mixed` | パラメータを取得（未設定時は `ParameterNotFoundException`） |
| `hasParameter(string $name)` | `bool` | パラメータが存在するか |
| `setAlias(string $alias, string $id)` | `self` | エイリアスを設定 |
| `addServiceProvider(ServiceProviderInterface $provider)` | `self` | サービスプロバイダーを追加 |

### Definition

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `getId()` | `string` | サービスIDを取得 |
| `setClass(?string $class)` | `self` | クラスを設定 |
| `getClass()` | `?string` | クラスを取得 |
| `setArgument(int\|string $key, mixed $value)` | `self` | 引数を設定 |
| `addArgument(mixed $argument)` | `self` | 引数を追加 |
| `getArguments()` | `array` | 引数一覧 |
| `setFactory(array $factory)` | `self` | ファクトリを設定 |
| `getFactory()` | `?array` | ファクトリを取得 |
| `addMethodCall(string $method, array $arguments = [])` | `self` | メソッドコールを追加 |
| `getMethodCalls()` | `array` | メソッドコール一覧 |
| `addTag(string $tag, array $attributes = [])` | `self` | タグを追加 |
| `getTags()` | `list<string>` | タグ名一覧 |
| `hasTag(string $tag)` | `bool` | タグの存在確認 |
| `autowire()` | `self` | オートワイヤリングを有効化 |
| `setAutowired(bool $autowired)` | `self` | オートワイヤリング設定 |
| `isAutowired()` | `bool` | オートワイヤリング有効か |
| `setPublic(bool $public)` | `self` | パブリック設定 |
| `isPublic()` | `bool` | パブリックか |
| `setLazy(bool $lazy)` | `self` | 遅延設定 |
| `isLazy()` | `bool` | 遅延か |
| `setAbstract(bool $abstract)` | `self` | 抽象設定 |
| `setDecoratedService(?string $id, ?string $renamedId = null, int $priority = 0)` | `self` | デコレーション設定 |

### Container

| メソッド | 戻り値 | 説明 |
|---------|--------|------|
| `get(string $id)` | `mixed` | サービスを取得（未登録時は `ServiceNotFoundException`） |
| `has(string $id)` | `bool` | サービスが存在するか |

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Container` | コンパイル済みサービスコンテナ（PSR-11 準拠） |
| `ContainerBuilder` | コンテナビルダー |
| `Definition` | サービス定義（fluent API） |
| `Reference` | サービス参照値オブジェクト |
| `ServiceDiscovery` | サービス自動検出 |
| `ServiceProviderInterface` | サービス登録カプセル化用インターフェース |
| `Dumper\PhpDumper` | コンパイル済みコンテナの PHP コード生成 |
| `Configurator\ContainerConfigurator` | Symfony スタイルのサービス設定 |
| `Configurator\DefaultsConfigurator` | デフォルト設定の構成 |
| `Configurator\ServiceConfigurator` | 個別サービスの構成 |
| `Configurator\PrototypeConfigurator` | ディレクトリスキャンの構成 |
| `Attribute\Exclude` | サービス登録除外マーカー |
| `Attribute\Autowire` | パラメータインジェクション |
| `Attribute\Env` | 環境変数インジェクション（`Autowire` 拡張） |
| `Attribute\Option` | WordPress option インジェクション（`Autowire` 拡張） |
| `Attribute\Constant` | PHP 定数インジェクション（`Autowire` 拡張） |
| `Attribute\AsAlias` | インターフェースエイリアス |
| `Compiler\CompilerPassInterface` | コンパイラーパスインターフェース |
| `WordPress\WordPressServiceProvider` | WordPress サービス登録 |
| `Exception\ServiceNotFoundException` | サービス未検出例外 |
| `Exception\ParameterNotFoundException` | パラメータ未検出例外 |
