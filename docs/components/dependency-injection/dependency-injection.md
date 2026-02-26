# DependencyInjection コンポーネント

**パッケージ:** `wppack/dependency-injection`
**名前空間:** `WpPack\Component\DependencyInjection\`
**レイヤー:** Infrastructure

PSR-11 準拠のサービスコンテナです。オートワイヤリング、アトリビュートベースのサービス登録、インターフェースバインディング、タグ付きサービス、コンパイラーパス、ファクトリサービス、サービスデコレーション、遅延サービス、WordPress グローバルのインジェクタブルサービスとしての提供などを提供します。

## インストール

```bash
composer require wppack/dependency-injection
```

## 基本コンセプト

### 従来の WordPress vs WpPack

```php
// 従来の WordPress - グローバル変数とシングルトン
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

// WpPack - オートワイヤリングによる依存性注入
use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService]
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

## アトリビュートベースのサービス登録

### `#[AsService]`

```php
use WpPack\Component\DependencyInjection\Attribute\AsService;

#[AsService]
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

### `#[AsService]` オプション

```php
#[AsService(
    public: true,           // コンテナから直接取得可能
    lazy: true,             // 遅延初期化
    tags: ['repository'],   // 一括操作用のタグ
)]
final class PostRepository
{
    // ...
}
```

### `#[Autowire]`

特定のパラメータ値を明示的にインジェクトします：

```php
use WpPack\Component\DependencyInjection\Attribute\Autowire;

#[AsService]
final class S3Client
{
    public function __construct(
        #[Autowire(env: 'AWS_REGION')]
        private readonly string $region,

        #[Autowire(env: 'S3_BUCKET')]
        private readonly string $bucket,

        #[Autowire(param: 'app.debug')]
        private readonly bool $debug,
    ) {}
}
```

### `#[AsAlias]`

インターフェースを具象実装にバインドします：

```php
use WpPack\Component\DependencyInjection\Attribute\AsAlias;

#[AsService]
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

`#[AsService]` やその他のマーカーアトリビュートを持つサービスをディレクトリから自動スキャンします：

```php
use WpPack\Component\DependencyInjection\ServiceDiscovery;

$discovery = new ServiceDiscovery($builder);

// ディレクトリをスキャン
$discovery->discover(
    namespace: 'App\\',
    directory: __DIR__ . '/src',
);

// 以下のアトリビュートを自動検出：
// #[AsService], #[AsHookSubscriber], #[AsMessageHandler],
// #[AsSchedule], #[AsEventListener], #[AsConfig], etc.
```

## タグ付きサービスとコンパイラーパス

### タグ付きサービス

一括操作のために関連するサービスをグループ化します：

```php
#[AsService(tags: ['notification.channel'])]
final class EmailNotification implements NotificationChannel
{
    public function send(string $to, string $message): bool
    {
        return wp_mail($to, 'Notification', $message);
    }
}

#[AsService(tags: ['notification.channel'])]
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

## ファクトリサービス

複雑なサービス生成を処理します：

```php
#[AsService]
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
#[AsService]
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

コンテナにスカラー値を登録します：

```php
$builder->setParameter('app.debug', true);
$builder->setParameter('app.name', 'My Plugin');

// 環境変数から
$builder->setParameter('aws.region', $_ENV['AWS_REGION'] ?? 'ap-northeast-1');
```

## WordPress との統合

WordPress グローバルとオブジェクトをインジェクタブルサービスとして登録します：

```php
use WpPack\Component\DependencyInjection\WordPress\WordPressServiceProvider;

$builder->addServiceProvider(new WordPressServiceProvider());

// 以下のサービスが利用可能になります：
// - wpdb ($wpdb インスタンス)
// - WP_Filesystem
// - etc.
```

### カスタム WordPress ファクトリ

```php
#[AsService]
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

初回使用時にのみインスタンス化されるサービス：

```php
#[AsService(lazy: true)]
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

```php
// 本番用にコンパイルしてダンプ
$builder->compile();
$dumper = new PhpDumper($builder);
$code = $dumper->dump(['class' => 'CachedContainer']);
file_put_contents(__DIR__ . '/cache/container.php', $code);

// 本番環境でキャッシュ済みコンテナを使用
if (file_exists(__DIR__ . '/cache/container.php')) {
    require_once __DIR__ . '/cache/container.php';
    $container = new CachedContainer();
} else {
    $container = $builder;
}
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
// #[AsService(tags: ['mailer.transport_factory'])] でも可
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

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Container` | コンパイル済みサービスコンテナ |
| `ContainerBuilder` | コンテナビルダー |
| `Definition` | サービス定義（fluent API） |
| `Reference` | サービス参照値オブジェクト |
| `Attribute\AsService` | サービス登録マーカー |
| `Attribute\Autowire` | パラメータインジェクション |
| `Attribute\AsAlias` | インターフェースエイリアス |
| `ServiceDiscovery` | サービス自動検出 |
| `Compiler\CompilerPassInterface` | コンパイラーパスインターフェース |
| `WordPress\WordPressServiceProvider` | WordPress サービス登録 |
