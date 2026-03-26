# WpPack Handler

A modern PHP request handler for WordPress. Provides intelligent request routing, static file serving, and comprehensive security features. Designed as a front controller similar to Symfony's `index.php`.

## Installation

```bash
composer require wppack/handler
```

## Quick Start

```php
// web/index.php
use WpPack\Component\Handler\Handler;

require dirname(__DIR__) . '/vendor/autoload.php';

$result = (new Handler())->run();
if ($result !== null) {
    require $result;
}
```

## Features

- **Front Controller Pattern** — Single entry point manages the full request lifecycle
- **Intelligent Routing** — Automatic handling of WordPress requests, static files, PHP files, and directories
- **Security First** — Built-in protection against directory traversal, null bytes, symlink escapes, and sensitive file access
- **Multisite Support** — URL rewriting for WordPress Multisite subdirectory installations
- **AWS Lambda Ready** — Automatic environment detection and `/tmp` directory setup
- **Kernel Integration** — Optionally initializes WpPack Kernel with the pre-built Request
- **Extensible** — Custom processors can be inserted at any point in the chain
- **PSR-3 Logging** — Error logging via `LoggerInterface`

## Configuration

```php
use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;
use WpPack\Component\HttpFoundation\Request;

$config = new Configuration([
    'web_root'        => __DIR__,
    'multisite'       => true,       // Enable multisite with defaults
    'lambda'          => true,       // Force Lambda mode
    'wordpress_index' => '/index.php',
    'wp_directory'    => '/wp',
]);

$result = (new Handler($config))->run();
if ($result !== null) {
    require $result;
}
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `web_root` | `string` | `getcwd()` | Web root directory |
| `wordpress_index` | `string` | `'/index.php'` | WordPress entry point |
| `wp_directory` | `string` | `'/wp'` | WordPress core directory |
| `index_files` | `string[]` | `['index.php', 'index.html', 'index.htm']` | Directory index files |
| `multisite` | `bool\|array` | `false` | Multisite configuration |
| `lambda` | `bool\|array` | auto-detect | Lambda environment |
| `security.allow_directory_listing` | `bool` | `false` | Allow directory listing |
| `security.check_symlinks` | `bool` | `true` | Validate symlinks |
| `security.blocked_patterns` | `string[]` | *(see below)* | Blocked URL regex patterns |

### Default Blocked Patterns

- `/\.git/`, `/\.env/`, `/\.htaccess/`
- `/composer\.(json|lock)/`, `/wp-config\.php/`
- `/readme\.(txt|html|md)/i`

## Processor Chain

Requests are processed through this chain in order:

1. **SecurityProcessor** — Validates paths and blocks attacks
2. **MultisiteProcessor** — Rewrites multisite URLs
3. **TrailingSlashProcessor** — Redirects directories to include trailing slash
4. **DirectoryProcessor** — Resolves directory requests to index files
5. **StaticFileProcessor** — Serves static files with correct MIME types
6. **PhpFileProcessor** — Handles direct PHP file requests
7. **WordPressProcessor** — Falls back to WordPress `index.php`

### Custom Processors

```php
use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Processor\ProcessorInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

class MaintenanceProcessor implements ProcessorInterface
{
    public function process(Request $request, Configuration $config): Request|Response|null
    {
        if (file_exists($config->get('web_root') . '/.maintenance')) {
            return new Response('Site under maintenance', 503);
        }

        return null;
    }
}

$handler = new Handler($config);
$handler->addProcessor(new MaintenanceProcessor(), priority: 1);

$result = $handler->run($request);
if ($result !== null) {
    require $result;
}
```

## Kernel Integration

When `wppack/kernel` is installed, Handler automatically pre-configures the Kernel with the Request before WordPress loads. This allows the Kernel to reuse the same Request instance during boot instead of creating a new one from globals.

```php
// No extra code needed — Handler calls Kernel::create($request) automatically
// when the Kernel class is available.
```

## Resources

- [Documentation](../../docs/components/handler/)
- [Report Issues](https://github.com/wppack-io/wppack/issues)
