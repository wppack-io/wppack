# wppack/multisite-url-fixer-plugin

Fix asset and content URLs for WordPress Multisite with Bedrock.

In Bedrock-style installations where WordPress is installed in a `/wp` subdirectory, multisite generates incorrect URLs for core assets (CSS, JS, fonts). This mu-plugin rewrites those URLs to include the `/wp` prefix.

## Installation

```bash
composer require wppack/multisite-url-fixer-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.x (Multisite)
- Bedrock-style directory structure (WordPress in `/wp` subdirectory)

## How It Works

Registers event listeners for the following WordPress filters:

| Filter | Fix |
|--------|-----|
| `style_loader_src` | Rewrites CSS URLs to include `/wp` |
| `script_loader_src` | Rewrites JS URLs to include `/wp` |
| `includes_url` | Fixes `wp-includes` URLs |
| `admin_url` | Fixes static file URLs in `wp-admin` |
| `option_home` | Removes trailing `/wp` from home URL |
| `option_siteurl` | Ensures `/wp` suffix on site URL |
| `network_site_url` | Fixes network admin URLs |

Only active when:
- WordPress is running as multisite
- WordPress is installed in a `/wp` subdirectory (detected via `ABSPATH`)

## License

MIT
