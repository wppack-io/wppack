# wppack/lambda-plugin

Lambda environment support for WordPress (URL rewriting, Site Health adjustments).

Activated only when the `LAMBDA_TASK_ROOT` environment variable is set (i.e., running on AWS Lambda).

## What It Does

- Enables URL rewriting (`got_url_rewrite` filter) so WordPress generates pretty permalinks
- Removes irrelevant Site Health checks:
  - `available_updates_disk_space` — Lambda has ephemeral storage
  - `update_temp_backup_writable` — no persistent temp directory
  - `background_updates` — not applicable in serverless

## Installation

```bash
composer require wppack/lambda-plugin
```

## Requirements

- PHP 8.2+
- WordPress 6.3 or higher

## License

MIT
