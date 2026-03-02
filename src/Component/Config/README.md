# WpPack Config

Type-safe configuration management using PHP attributes. Maps environment variables, WordPress options, and constants to readonly DTO properties.

## Installation

```bash
composer require wppack/config
```

## Usage

### Config Class

```php
use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;
use WpPack\Component\Config\Attribute\Option;
use WpPack\Component\Config\Attribute\Constant;

#[AsConfig]
final readonly class AppConfig
{
    public function __construct(
        #[Env('APP_NAME')]
        public string $name,
        #[Env('APP_DEBUG')]
        public bool $debug = false,
        #[Option('my_plugin_settings.api_endpoint')]
        public string $apiEndpoint = 'https://api.example.com',
        #[Constant('DB_HOST')]
        public string $dbHost = 'localhost',
    ) {}
}
```

### ConfigResolver

```php
use WpPack\Component\Config\ConfigResolver;

$resolver = new ConfigResolver();
$config = $resolver->resolve(AppConfig::class);

$config->name;        // string
$config->debug;       // bool
$config->apiEndpoint; // string
```

### DI Integration

With `wppack/dependency-injection`, `#[AsConfig]` classes are auto-discovered and registered as services:

```php
$builder->addCompilerPass(new RegisterConfigClassesPass());
$container = $builder->compile();

$config = $container->get(AppConfig::class);
```

## Documentation

See [docs/components/config/](../../docs/components/config/) for full documentation.

## License

MIT
