# wppack/theme-directory-plugin

Register the default theme directory for Bedrock-style WordPress installations.

In Bedrock, themes are located at `web/wp-content/themes/` but WordPress core looks for themes inside its own `wp/wp-content/themes/` directory. This mu-plugin registers the core theme directory so that bundled themes (e.g., Twenty Twenty-Five) are available.

## What It Does

- Calls `register_theme_directory(ABSPATH . 'wp-content/themes')` to make WordPress core themes available
- Only activates when `WP_DEFAULT_THEME` is not defined

## Installation

```bash
composer require wppack/theme-directory-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.7 or higher

## License

MIT
