# Local WordPress Development Environment

Local development environment for testing WpPack components in a browser.

## Setup

```bash
# 1. Install WordPress via Composer (placed in web/wp/)
composer install

# 2. Start MySQL via Docker (wppack_dev DB is created automatically)
docker compose up -d --wait

# 3. Install WordPress
vendor/bin/wp core install \
    --path=web/wp \
    --url=http://localhost:8080 \
    --title="WpPack Dev" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@localhost.test \
    --skip-email

# 4. Start the development server
php -S localhost:8080 -t web web/handler.php
```

## Access

- Site: http://localhost:8080
- Admin: http://localhost:8080/wp/wp-admin/ (admin / admin)

## Structure

| Path | Description |
|------|-------------|
| `web/wp/` | WordPress core (installed by Composer, gitignored) |
| `web/wp-config.php` | WordPress configuration (DB: `wppack_dev`) |
| `web/handler.php` | Front controller (WpPack Handler) |
| `web/wp-content/mu-plugins/` | Loads Composer autoloader |
| `web/wp-content/plugins/` | Symlinks to `src/Plugin/*` |

## Notes

- MySQL runs on `tmpfs`, so re-run `wp core install` after restarting the container
- Uses a separate DB (`wppack_dev`) from the test DB (`wppack_test`)
