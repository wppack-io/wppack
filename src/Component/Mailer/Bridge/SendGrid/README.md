# SendGrid Mailer

SendGrid transport implementation for WpPack Mailer.

## Installation

```bash
composer require wppack/sendgrid-mailer
```

## DSN Configuration

```php
// wp-config.php

// API (recommended)
define('MAILER_DSN', 'sendgrid://API_KEY@default');

// SMTP
define('MAILER_DSN', 'sendgrid+smtp://apikey:API_KEY@default');
```

| DSN | Transport | Method |
|-----|-----------|--------|
| `sendgrid://` | SendGridApiTransport | v3 Mail Send API (recommended) |
| `sendgrid+https://` | SendGridApiTransport | Alias for `sendgrid://` |
| `sendgrid+api://` | SendGridApiTransport | Alias for `sendgrid://` |
| `sendgrid+smtp://` | SendGridSmtpTransport | SMTP TLS (port 587) |
| `sendgrid+smtps://` | SendGridSmtpTransport | SMTP SSL (port 465) |

No external SDK required. API calls are made via `wppack/http-client`.

## Dependencies

- `wppack/mailer` ^1.0
- `wppack/http-client` ^1.0

## Documentation

For details, see [docs/components/mailer/sendgrid-mailer.md](../../../docs/components/mailer/sendgrid-mailer.md).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
