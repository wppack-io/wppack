# Amazon Mailer

Amazon SES transport implementation for WpPack Mailer.

## Installation

```bash
composer require wppack/amazon-mailer
```

## DSN Configuration

```php
// wp-config.php
define('MAILER_DSN', 'ses://ACCESS_KEY:SECRET_KEY@default?region=ap-northeast-1');
```

| DSN | Transport | Method |
|-----|-----------|--------|
| `ses://` | SesTransport | Raw MIME delivery (recommended) |
| `ses+https://` | SesTransport | Alias for `ses://` |
| `ses+api://` | SesApiTransport | Structured API delivery |
| `ses+smtp://` | SesSmtpTransport | SMTP connection |
| `ses+smtps://` | SesSmtpTransport | SMTP SSL (port 465) |

## Dependencies

- `wppack/mailer` ^1.0
- `async-aws/ses` ^1.14

## Documentation

For details, see [docs/components/mailer/amazon-mailer.md](../../../docs/components/mailer/amazon-mailer.md).

## Resources

- [Issues](https://github.com/wppack-io/wppack/issues)
- [Pull Requests](https://github.com/wppack-io/wppack/pulls)

Developed in the main repository [wppack-io/wppack](https://github.com/wppack-io/wppack).
