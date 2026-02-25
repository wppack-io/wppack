# Config Component

**Package:** `wppack/config`
**Namespace:** `WpPack\Component\Config\`
**Layer:** Infrastructure

型安全な設定管理コンポーネント。PHP アトリビュートを使用して、環境変数・WordPress オプション・WordPress 定数をクラスプロパティに自動的にマッピングします。

## インストール

```bash
composer require wppack/config
```

## 基本コンセプト

### 従来の WordPress と WpPack の比較

```php
// 従来の WordPress - 散在する設定取得
$apiKey = defined('MY_API_KEY') ? MY_API_KEY : '';
$debug = defined('WP_DEBUG') && WP_DEBUG;
$option = get_option('my_plugin_settings', []);
$value = $option['some_key'] ?? 'default';

// WpPack Config - 型安全でアトリビュートベース
use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;
use WpPack\Component\Config\Attribute\Option;

#[AsConfig(prefix: 'my_plugin')]
final readonly class MyPluginConfig
{
    public function __construct(
        #[Env('MY_API_KEY')]
        public string $apiKey = '',

        #[Env('WP_DEBUG')]
        public bool $debug = false,

        #[Option('my_plugin_settings.some_key')]
        public string $someValue = 'default',
    ) {}
}

// DI コンテナから型安全にアクセス
$config = $container->get(MyPluginConfig::class);
$config->apiKey;   // string
$config->debug;    // bool
```

## 設定クラス

### `#[AsConfig]` アトリビュート

設定クラスとしてマークし、自動検出・解決の対象にします。

```php
use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;
use WpPack\Component\Config\Attribute\Option;

#[AsConfig(prefix: 'aws')]
final readonly class AwsConfig
{
    public function __construct(
        #[Env('AWS_ACCESS_KEY_ID')]
        public string $accessKeyId = '',

        #[Env('AWS_SECRET_ACCESS_KEY')]
        public string $secretAccessKey = '',

        #[Env('AWS_REGION')]
        public string $region = 'ap-northeast-1',
    ) {}
}
```

## 設定ソース

### 環境変数 (`#[Env]`)

環境変数の値を取得し、宣言された PHP 型に自動キャストします。

```php
use WpPack\Component\Config\Attribute\Env;

// デフォルト値なし（必須） - 環境変数が未設定の場合、ConfigResolver が例外をスローします
#[Env('DATABASE_URL')]
public string $databaseUrl;

// デフォルト値あり（任意） - 環境変数が未設定の場合、デフォルト値が使用されます
#[Env('APP_DEBUG')]
public bool $debug = false;

#[Env('MAX_RETRIES')]
public int $maxRetries = 3;
```

### WordPress オプション (`#[Option]`)

`get_option()` から値を取得します。ドット記法でネストされた値にアクセスできます。

```php
use WpPack\Component\Config\Attribute\Option;

// 単一オプション
#[Option('blogname')]
public string $siteName;

// ネストされたオプション（ドット記法）
#[Option('my_plugin_settings.api_endpoint')]
public string $apiEndpoint = 'https://api.example.com';

// シリアライズされた配列オプション
#[Option('my_plugin_settings')]
public array $allSettings = [];
```

### WordPress 定数 (`#[Constant]`)

`wp-config.php` で `define()` された値を設定クラスで使用します。

```php
use WpPack\Component\Config\Attribute\Constant;

#[AsConfig]
final readonly class DatabaseConfig
{
    public function __construct(
        #[Constant('DB_HOST')]
        public string $host,

        #[Constant('DB_NAME')]
        public string $name,

        #[Constant('DB_USER')]
        public string $user,

        #[Constant('DB_PASSWORD')]
        public string $password,

        #[Constant('DB_CHARSET')]
        public string $charset = 'utf8mb4',
    ) {}
}
```

## ConfigResolver

設定クラスを解決し、各ソースから値を注入します。デフォルト値が設定されていない必須プロパティ（`#[Env]`、`#[Option]`、`#[Constant]`）の値が見つからない場合、`ConfigResolverException` をスローします。

```php
use WpPack\Component\Config\ConfigResolver;

$resolver = new ConfigResolver();

// 環境変数が自動的に注入される
$awsConfig = $resolver->resolve(AwsConfig::class);

// デフォルト値のないプロパティで値が未設定の場合、例外がスローされる
// 例: AWS_ACCESS_KEY_ID が未設定 → ConfigResolverException
```

## バリデーション

設定クラスにバリデーションロジックを組み込むことができます。

```php
use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;

#[AsConfig]
final readonly class AppConfig
{
    public function __construct(
        #[Env('APP_NAME')]
        public string $name,

        #[Env('APP_PORT')]
        public int $port = 8080,
    ) {}

    public function validate(): void
    {
        if ($this->port < 1 || $this->port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }
    }
}
```

## 主要クラス

| クラス | 説明 |
|-------|------|
| `Attribute\AsConfig` | 設定クラスマーカー |
| `Attribute\Env` | 環境変数ソース |
| `Attribute\Option` | WordPress オプションソース |
| `Attribute\Constant` | WordPress 定数ソース |
| `ConfigResolver` | 設定値の解決（未設定の必須値で例外をスロー） |
