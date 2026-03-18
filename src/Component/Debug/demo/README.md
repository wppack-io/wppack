# Debug Component Demos

Demo pages for browsing Debug component features.

## Prerequisites

MySQL must be running:

```bash
docker compose up -d --wait
```

## Starting the server

```bash
php -S localhost:8080 -t src/Component/Debug/demo/
```

## Demo pages

### exception.php — Exception debug page

Debug page with chained exceptions (PDOException → RuntimeException).

- Code snippets with throw-line highlighting
- Stack trace with accordion-expandable code view
- Previous exception tabs (exception chain)
- Request / Environment / Performance tabs

```
http://localhost:8080/exception.php
```

### wp-die.php — wp_die() handling

Demo of `WpDieHandler` intercepting `wp_die()` to render a debug page. Switch scenarios via the `?scenario` parameter.

| Scenario | URL | Display class | HTTP |
|----------|-----|--------------|------|
| Permission denied | `?scenario=permission` | `WP_Error (forbidden)` | 403 |
| DB connection error | `?scenario=db` | `WP_Error (db_connect_fail)` | 500 |
| Nonce failure | `?scenario=nonce` | `wp_die()` | 403 |
| Generic error | `?scenario=default` | `wp_die()` | 500 |

```
http://localhost:8080/wp-die.php?scenario=permission
http://localhost:8080/wp-die.php?scenario=db
http://localhost:8080/wp-die.php?scenario=nonce
```

### toolbar.php — Debug toolbar

Toolbar demo displaying all DataCollectors with fake data. L-shaped layout (left sidebar + content area + bottom bar) with Gutenberg-style design.

```
http://localhost:8080/toolbar.php
```
